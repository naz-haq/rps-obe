<?php

namespace App\Services\Ai;

use App\Models\AiInteraksi;
use App\Models\AiKredensial;
use App\Models\DokumenChunk;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Layanan embedding untuk RAG (Blueprint 7.5). Menghasilkan vektor teks via
 * OpenAI text-embedding-3-small, menyimpannya di DOKUMEN_CHUNK.embedding (JSON),
 * dan melakukan pencarian kemiripan kosinus DI DALAM APLIKASI (tanpa pgvector,
 * sesuai keputusan MySQL). Kredensial: BYOK tenant > env server > mock dev
 * (vektor deterministik agar cosine stabil offline). Setiap panggilan dicatat
 * ke AI_INTERAKSI (mode 'embedding').
 */
class EmbeddingService
{
    /**
     * Embed satu teks menjadi vektor.
     *
     * @return array{embedding:array<int,float>, tokens:int, biaya:float, provider:string, model:string, mock:bool}
     */
    public function embed(string $text, array $context = []): array
    {
        $cfg = config('ai.embedding');
        $institusiId = $context['institusi_id'] ?? null;
        $userId = $context['user_id'] ?? null;

        $cred = $this->resolveCredentials($cfg, $institusiId, $userId);

        if ($cred === null) {
            $vec = $this->mockVector($text, (int) $cfg['dimensions']);
            $tokens = $this->estimateTokens($text);
            $model = $cfg['model'] . '-mock';
            $this->log($context, 'mock', $model, $tokens, 0.0, false);

            return ['embedding' => $vec, 'tokens' => $tokens, 'biaya' => 0.0, 'provider' => 'mock', 'model' => $model, 'mock' => true];
        }

        [$apiKey, $baseUrl, $model] = $cred;
        $provider = $cfg['provider'];

        // Payload embedding BEDA per provider:
        // - OpenAI text-embedding-3-* mendukung 'dimensions' (potong dimensi).
        // - NVIDIA NIM (nv-embedqa dll) WAJIB 'input_type' (query|passage) +
        //   'truncate', TIDAK menerima 'dimensions', dan minta input berupa array.
        $payload = ['model' => $model, 'input' => $text];
        if ($provider === 'nvidia') {
            $payload['input'] = [$text];
            $payload['input_type'] = $context['input_type'] ?? 'query';
            $payload['truncate'] = 'END';
            $payload['encoding_format'] = 'float';
        } else {
            $payload['dimensions'] = (int) $cfg['dimensions'];
        }

        try {
            $resp = Http::withToken($apiKey)
                ->timeout(60)
                ->post(rtrim($baseUrl, '/') . '/embeddings', $payload);
        } catch (\Throwable $e) {
            $this->log($context, $provider, $model, 0, 0.0, true);
            if (config('ai.fallback_to_mock')) {
                $vec = $this->mockVector($text, (int) $cfg['dimensions']);

                return ['embedding' => $vec, 'tokens' => $this->estimateTokens($text), 'biaya' => 0.0, 'provider' => 'mock', 'model' => $cfg['model'] . '-mock', 'mock' => true];
            }
            throw new RuntimeException('Gagal embedding (HTTP): ' . $e->getMessage(), 0, $e);
        }

        $data = $resp->json();
        $vec = $data['data'][0]['embedding'] ?? null;

        if (! is_array($vec)) {
            $this->log($context, $provider, $model, 0, 0.0, true);
            if (config('ai.fallback_to_mock')) {
                $vec = $this->mockVector($text, (int) $cfg['dimensions']);

                return ['embedding' => $vec, 'tokens' => $this->estimateTokens($text), 'biaya' => 0.0, 'provider' => 'mock', 'model' => $cfg['model'] . '-mock', 'mock' => true];
            }
            $msg = is_array($data['error'] ?? null) ? ($data['error']['message'] ?? 'error') : 'respons embedding tidak valid';
            throw new RuntimeException("Gagal embedding: {$msg}");
        }

        $tokens = (int) ($data['usage']['prompt_tokens'] ?? $this->estimateTokens($text));
        $biaya = round($tokens / 1_000_000 * (float) $cfg['pricing']['input'], 6);
        $this->log($context, $provider, $model, $tokens, $biaya, false);

        return ['embedding' => $vec, 'tokens' => $tokens, 'biaya' => $biaya, 'provider' => $provider, 'model' => $model, 'mock' => false];
    }

    /**
     * Hitung & simpan embedding untuk satu chunk dokumen.
     */
    public function embedChunk(DokumenChunk $chunk, array $context = []): DokumenChunk
    {
        $context['institusi_id'] = $context['institusi_id'] ?? $chunk->dokumen?->institusi_id;
        $context['entity_type'] = $context['entity_type'] ?? 'DokumenChunk';
        $context['entity_id'] = $context['entity_id'] ?? $chunk->id;
        $context['mode'] = $context['mode'] ?? 'embedding';
        // Dokumen yang di-INDEKS = 'passage' (wajib utk model retrieval NVIDIA).
        $context['input_type'] = $context['input_type'] ?? 'passage';

        $r = $this->embed($chunk->teks, $context);

        $chunk->update([
            'embedding'   => $r['embedding'],
            'token_count' => $chunk->token_count ?? $r['tokens'],
        ]);

        return $chunk;
    }

    /**
     * Cari chunk paling relevan terhadap query via kosinus in-app. Dibatasi
     * dokumen milik tenant (atau global). Retrieval untuk grounding validator.
     *
     * @param  array{dokumen_id?:int, min_score?:float}  $opts
     * @return array<int,array{chunk:DokumenChunk, score:float}>
     */
    public function search(int $institusiId, string $query, int $topK = 5, array $opts = []): array
    {
        // Query pencarian = 'query' (asimetris terhadap 'passage' pd model NVIDIA).
        $r = $this->embed($query, ['institusi_id' => $institusiId, 'mode' => 'embedding:query', 'input_type' => 'query']);
        $qvec = $r['embedding'];

        $chunks = DokumenChunk::query()
            ->whereNotNull('embedding')
            ->when($opts['dokumen_id'] ?? null, fn($q, $id) => $q->where('dokumen_id', $id))
            ->whereHas('dokumen', fn($q) => $q->where(
                fn($qq) => $qq->where('institusi_id', $institusiId)->orWhereNull('institusi_id')
            ))
            ->get();

        $minScore = (float) ($opts['min_score'] ?? 0.0);
        $scored = [];
        foreach ($chunks as $c) {
            $score = $this->cosine($qvec, (array) $c->embedding);
            if ($score >= $minScore) {
                $scored[] = ['chunk' => $c, 'score' => $score];
            }
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, max(1, $topK));
    }

    /**
     * Kemiripan kosinus dua vektor (0..1 untuk vektor non-negatif; -1..1 umum).
     */
    public function cosine(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $x = (float) $a[$i];
            $y = (float) $b[$i];
            $dot += $x * $y;
            $na += $x * $x;
            $nb += $y * $y;
        }

        if ($na <= 0.0 || $nb <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($na) * sqrt($nb));
    }

    /**
     * @return array{0:string,1:?string,2:string}|null [apiKey, baseUrl, model] atau null bila mock
     */
    private function resolveCredentials(array $cfg, ?int $institusiId, ?int $userId): ?array
    {
        $provider = $cfg['provider'];
        $providerCfg = config("ai.providers.{$provider}");
        $apiKey = $providerCfg['api_key'] ?? null;

        if ($institusiId) {
            $kred = AiKredensial::query()
                ->where('institusi_id', $institusiId)
                ->where('provider', $provider)
                ->where('aktif', true)
                ->when($userId, fn($q) => $q->where(
                    fn($qq) => $qq->where('user_id', $userId)->orWhereNull('user_id')
                ))
                ->when(! $userId, fn($q) => $q->whereNull('user_id'))
                ->orderByRaw('user_id IS NULL')
                ->first();

            if ($kred) {
                $apiKey = $kred->api_key_encrypted; // cast 'encrypted' -> plaintext
            }
        }

        if (empty($apiKey)) {
            if (config('ai.fallback_to_mock')) {
                return null; // pakai mock
            }
            throw new RuntimeException("Tidak ada kredensial embedding untuk provider '{$provider}'.");
        }

        return [$apiKey, $providerCfg['base_url'] ?? null, $cfg['model']];
    }

    /**
     * Vektor tiruan deterministik (seed dari teks) & ternormalisasi L2, agar
     * cosine konsisten antar-pemanggilan saat dev tanpa API key.
     *
     * @return array<int,float>
     */
    private function mockVector(string $text, int $dims): array
    {
        mt_srand(crc32($text));
        $vec = [];
        for ($i = 0; $i < $dims; $i++) {
            $vec[] = (mt_rand(0, 2_000_000) / 1_000_000) - 1.0; // [-1,1]
        }
        mt_srand(); // kembalikan keacakan normal

        $norm = sqrt(array_sum(array_map(fn($v) => $v * $v, $vec)));
        if ($norm > 0.0) {
            $vec = array_map(fn($v) => $v / $norm, $vec);
        }

        return $vec;
    }

    private function estimateTokens(string $s): int
    {
        return (int) max(1, ceil(mb_strlen($s) / 4));
    }

    private function log(array $context, string $provider, string $model, int $tokens, float $biaya, bool $gagal): void
    {
        AiInteraksi::create([
            'institusi_id' => $context['institusi_id'] ?? null,
            'user_id'      => $context['user_id'] ?? null,
            'entity_type'  => $context['entity_type'] ?? null,
            'entity_id'    => $context['entity_id'] ?? null,
            'mode'         => $context['mode'] ?? 'embedding',
            'provider'     => $provider,
            'model'        => $model,
            'tokens_in'    => $tokens,
            'tokens_out'   => 0,
            'biaya'        => round($biaya, 6),
            'status'       => $gagal ? 'gagal' : 'sukses',
        ]);
    }
}
