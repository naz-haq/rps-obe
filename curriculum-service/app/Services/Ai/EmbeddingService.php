<?php

namespace App\Services\Ai;

use App\Models\AiInteraksi;
use App\Models\AiKredensial;
use App\Models\AiPengaturan;
use App\Models\DokumenChunk;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Layanan embedding untuk RAG (Blueprint 7.5). Menghasilkan vektor teks via
 * OpenAI text-embedding-3-small, menyimpannya di DOKUMEN_CHUNK.embedding (JSON),
 * dan melakukan pencarian kemiripan kosinus DI DALAM APLIKASI (tanpa pgvector,
 * sesuai keputusan MySQL). Kredensial: BYOK tenant > env server > mock dev
 * (vektor deterministik agar cosine stabil offline). Cache hit tidak dicatat
 * ulang ke AI_INTERAKSI. Chunk legacy tanpa embedding_identity WAJIB diindeks
 * ulang; identitas modelnya tidak boleh ditebak dari konfigurasi saat ini.
 */
class EmbeddingService
{
    /**
     * Embed satu teks menjadi vektor.
     *
     * @return array{embedding:array<int,float>, tokens:int, biaya:float, provider:string, model:string, mock:bool, cache_hit:bool}
     */
    public function embed(string $text, array $context = []): array
    {
        // Resolve credentials BEFORE consulting cache, including user BYOK scope.
        [$cfg, $cred, $identity] = $this->resolveEmbedding($context);

        return $this->embedResolved($text, $context, $cfg, $cred, $identity);
    }

    private function embedResolved(string $text, array $context, array $cfg, ?array $cred, array $identity): array
    {
        $ttl = (int) config('ai.embedding_cache_ttl', 86400);
        $key = 'ai:embedding:v1:' . $this->hash([
            $context['institusi_id'] ?? null,
            $context['user_id'] ?? null,
            $identity,
            hash('sha256', $text),
        ]);
        $generate = fn() => $this->requestEmbedding($text, $context, $cfg, $cred);

        if ($ttl <= 0 || $this->forceRequested($context)) {
            // A forced reindex must bypass both persistent and transient reuse.
            $this->forgetCache($key);
            $result = $generate();
            if ($ttl > 0 && $this->resultMatches($result, $identity)) {
                $this->writeCache($key, $result, $ttl);
            }

            return $result;
        }

        $read = function () use ($key, $identity): ?array {
            $hit = $this->readCache($key);
            if (! is_array($hit) || ! $this->resultMatches($hit, $identity)) {
                return null;
            }

            return array_replace($hit, ['tokens' => 0, 'biaya' => 0.0, 'cache_hit' => true]);
        };
        if (($hit = $read()) !== null) {
            return $hit;
        }

        $run = function () use ($read, $generate, $identity, $key, $ttl): array {
            if (($hit = $read()) !== null) {
                return $hit;
            }
            $result = $generate();
            // In particular, NEVER cache a failed real request's mock fallback.
            if ($this->resultMatches($result, $identity)) {
                $this->writeCache($key, $result, $ttl);
            }

            return $result;
        };

        $lock = null;
        try {
            $store = Cache::store()->getStore();
            if ($store instanceof LockProvider) {
                $lock = $store->lock($key . ':lock', 90);
            }
        } catch (\Throwable) {
            // Cache infrastructure is optional; don't expose backend details.
        }
        if ($lock === null) {
            return $run();
        }

        try {
            $lock->block(65); // Longer than the provider's 60 second timeout.
        } catch (LockTimeoutException) {
            if (($hit = $read()) !== null) {
                return $hit;
            }
            throw new RuntimeException('Embedding identik masih diproses. Coba lagi.');
        } catch (\Throwable) {
            return $run();
        }

        try {
            return $run();
        } finally {
            try {
                $lock->release();
            } catch (\Throwable) {
                // Never mask the original provider exception with a lock error.
            }
        }
    }

    private function requestEmbedding(string $text, array $context, array $cfg, ?array $cred): array
    {
        if ($cred === null) {
            $result = $this->mockResult($text, $cfg);
            $this->log($context, 'mock', $result['model'], $result['tokens'], 0.0, false);

            return $result;
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
        } catch (\Throwable) {
            $this->log($context, $provider, $model, 0, 0.0, true);
            if (config('ai.fallback_to_mock')) {
                return $this->mockResult($text, $cfg);
            }
            // Transport messages may contain URLs/credentials; don't propagate them.
            throw new RuntimeException('Gagal embedding (HTTP). Coba lagi.');
        }

        $data = $resp->json();
        $vec = $data['data'][0]['embedding'] ?? null;

        if (! $resp->successful() || ! $this->validVector($vec, (int) $cfg['dimensions'])) {
            $this->log($context, $provider, $model, 0, 0.0, true);
            if (config('ai.fallback_to_mock')) {
                return $this->mockResult($text, $cfg);
            }
            throw new RuntimeException("Gagal embedding (HTTP {$resp->status()}): respons atau dimensi vektor tidak valid.");
        }

        $tokens = (int) ($data['usage']['prompt_tokens'] ?? $this->estimateTokens($text));
        $biaya = round($tokens / 1_000_000 * (float) $cfg['pricing']['input'], 6);
        $this->log($context, $provider, $model, $tokens, $biaya, false);

        return ['embedding' => $vec, 'tokens' => $tokens, 'biaya' => $biaya, 'provider' => $provider, 'model' => $model, 'mock' => false, 'cache_hit' => false];
    }

    private function mockResult(string $text, array $cfg): array
    {
        return [
            'embedding' => $this->mockVector($text, (int) $cfg['dimensions']),
            'tokens' => $this->estimateTokens($text),
            'biaya' => 0.0,
            'provider' => 'mock',
            'model' => $cfg['model'] . '-mock',
            'mock' => true,
            'cache_hit' => false,
        ];
    }

    /** Secrets never enter identity metadata or cache keys/values. */
    private function resolveEmbedding(array $context): array
    {
        $cfg = $this->effectiveConfiguration($context['institusi_id'] ?? null);
        if ((int) $cfg['dimensions'] < 1) {
            throw new RuntimeException('Dimensi embedding harus positif.');
        }
        $cred = $this->resolveCredentials($cfg, $context['institusi_id'] ?? null, $context['user_id'] ?? null);
        $identity = [
            'version' => 1,
            'provider' => $cred === null ? 'mock' : $cfg['provider'],
            'model' => $cred === null ? $cfg['model'] . '-mock' : $cred[2],
            'dimensions' => (int) $cfg['dimensions'],
            'mock' => $cred === null,
            // Hash even the endpoint: custom URLs can contain sensitive data.
            'endpoint_hash' => hash('sha256', rtrim((string) ($cred[1] ?? config("ai.providers.{$cfg['provider']}.base_url")), '/')),
            'input_type' => $context['input_type'] ?? 'query',
        ];

        return [$cfg, $cred, $identity];
    }

    private function forceRequested(array $context): bool
    {
        return ! empty($context['force']) || ! empty($context['no_cache']) || ! empty($context['force_reembed']);
    }

    private function hash(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }

    private function validVector(mixed $vector, int $dimensions): bool
    {
        if (! is_array($vector) || ! array_is_list($vector) || $dimensions < 1 || count($vector) !== $dimensions) {
            return false;
        }
        $norm = 0.0;
        foreach ($vector as $value) {
            if ((! is_float($value) && ! is_int($value)) || ! is_finite((float) $value)) {
                return false;
            }
            $norm += $value * $value;
        }

        return $norm > 0.0 && is_finite($norm);
    }

    private function resultMatches(array $result, array $identity): bool
    {
        return ($result['provider'] ?? null) === $identity['provider']
            && ($result['model'] ?? null) === $identity['model']
            && ($result['mock'] ?? null) === $identity['mock']
            && $this->validVector($result['embedding'] ?? null, $identity['dimensions']);
    }

    private function readCache(string $key): mixed
    {
        try {
            return Cache::get($key);
        } catch (\Throwable) {
            return null;
        }
    }

    private function writeCache(string $key, array $value, int $ttl): void
    {
        try {
            Cache::put($key, $value, $ttl);
        } catch (\Throwable) {
            // Cache outages must not turn a successful paid request into a retry.
        }
    }

    private function forgetCache(string $key): void
    {
        try {
            Cache::forget($key);
        } catch (\Throwable) {
            // Force still bypasses reads when the cache is unavailable.
        }
    }

    /**
     * Konfigurasi embedding efektif: tenant -> global -> environment.
     *
     * @return array{provider:string,model:string,dimensions:int,pricing:array}
     */
    public function effectiveConfiguration(?int $institusiId = null): array
    {
        $cfg = (array) config('ai.embedding');
        $records = AiPengaturan::query()
            ->whereNull('institusi_id')
            ->when($institusiId, fn($q) => $q->orWhere('institusi_id', $institusiId))
            ->orderByRaw('institusi_id IS NULL DESC')
            ->get();

        foreach ($records as $record) {
            if (! $record->embedding_provider || ! $record->embedding_model || ! $record->embedding_dimensions) {
                continue;
            }

            $cfg = [
                'provider' => $record->embedding_provider,
                'model' => $record->embedding_model,
                'dimensions' => (int) $record->embedding_dimensions,
                'pricing' => $this->embeddingPricing($record->embedding_provider, $record->embedding_model, $cfg['pricing'] ?? []),
            ];
        }

        return $cfg;
    }

    private function embeddingPricing(string $provider, string $model, array $fallback): array
    {
        foreach ((array) config('ai.embedding_models', []) as $option) {
            if (($option['provider'] ?? null) === $provider && ($option['model'] ?? null) === $model) {
                return (array) ($option['pricing'] ?? $fallback);
            }
        }

        return $fallback;
    }

    /**
     * Hitung & simpan embedding untuk satu chunk dokumen. force/no_cache di
     * context (atau argumen force) melewati cache persisten DAN cache teks.
     */
    public function embedChunk(DokumenChunk $chunk, array $context = [], bool $force = false): DokumenChunk
    {
        $context['institusi_id'] = $context['institusi_id'] ?? $chunk->dokumen?->institusi_id;
        $context['entity_type'] = $context['entity_type'] ?? 'DokumenChunk';
        $context['entity_id'] = $context['entity_id'] ?? $chunk->id;
        $context['mode'] = $context['mode'] ?? 'embedding';
        // Dokumen yang di-INDEKS = 'passage' (wajib utk model retrieval NVIDIA).
        $context['input_type'] = $context['input_type'] ?? 'passage';
        $context['force'] = $force || $this->forceRequested($context);
        [$cfg, $cred, $identity] = $this->resolveEmbedding($context);

        if (! $context['force'] && $this->chunkMatches($chunk, $identity)) {
            return $chunk;
        }

        $r = $this->embedResolved($chunk->teks, $context, $cfg, $cred, $identity);
        // A fallback is explicitly marked mock, never attributed to a real model.
        $identity['provider'] = $r['provider'];
        $identity['model'] = $r['model'];
        $identity['mock'] = $r['mock'];

        $chunk->update([
            'embedding'   => $r['embedding'],
            'embedding_identity' => $this->chunkIdentity($identity, $chunk->teks),
            'token_count' => $chunk->token_count ?? $r['tokens'],
        ]);

        return $chunk;
    }

    /**
     * Cari chunk paling relevan terhadap query via kosinus in-app. Dibatasi
     * dokumen milik tenant (atau global). Retrieval untuk grounding validator.
     *
     * @param  array{dokumen_id?:int, dokumen_ids?:array<int,int>, min_score?:float, sumber_konten?:bool, user_id?:int}  $opts
     * @return array<int,array{chunk:DokumenChunk, score:float}>
     */
    public function search(int $institusiId, string $query, int $topK = 5, array $opts = []): array
    {
        // An explicit empty allowlist means NO documents, not all documents.
        if (array_key_exists('dokumen_ids', $opts)) {
            $opts['dokumen_ids'] = array_values(array_unique(array_map('intval', (array) $opts['dokumen_ids'])));
            sort($opts['dokumen_ids'], SORT_NUMERIC);
            if ($opts['dokumen_ids'] === []) {
                return [];
            }
        }
        $chunks = $this->scopedChunks($institusiId, $opts);
        if ($chunks->isEmpty()) {
            return [];
        }

        $context = [
            'institusi_id' => $institusiId,
            'user_id' => $opts['user_id'] ?? null,
            'mode' => 'embedding:query',
            'input_type' => 'query'
        ];
        [$cfg, $cred, $identity] = $this->resolveEmbedding($context);
        // Query/passages are deliberately asymmetric for retrieval models.
        $passageIdentity = array_replace($identity, ['input_type' => 'passage']);
        $eligible = fn($items) => $items->filter(fn($chunk) => $this->chunkMatches($chunk, $passageIdentity));
        $chunks = $eligible($chunks);
        if ($chunks->isEmpty()) {
            return []; // No paid query for legacy/stale/incompatible vectors.
        }

        $topK = max(1, $topK);
        $minScore = (float) ($opts['min_score'] ?? 0.0);
        $ttl = (int) config('ai.embedding_retrieval_cache_ttl', 300);
        $cacheKey = fn($items) => 'ai:retrieval:v1:' . $this->hash([
            $institusiId,
            $context['user_id'],
            hash('sha256', $query),
            $identity,
            $topK,
            $minScore,
            (bool) ($opts['sumber_konten'] ?? false),
            isset($opts['dokumen_id']) ? (int) $opts['dokumen_id'] : null,
            $opts['dokumen_ids'] ?? null,
            // Deterministic complete scoped fingerprint; NOT max(updated_at).
            // This still scans DB vectors: it is NOT a vector index. Callers
            // filtering by MK must supply the current linked dokumen_ids.
            $items->map(fn($c) => [
                $c->id,
                $c->dokumen_id,
                hash('sha256', $c->teks),
                $c->getRawOriginal('updated_at'),
                $this->hash($c->embedding),
                $c->embedding_identity,
                $c->dokumen->getAttributes(),
            ])->values()->all(),
        ]);
        if ($ttl > 0 && is_array($cached = $this->readCache($cacheKey($chunks)))) {
            // Cache contains IDs/scores only. Models come from THIS authorized
            // DB snapshot, so deletion, tenant/source changes and filters apply.
            $current = $chunks->keyBy('id');

            return collect($cached)->filter(fn($row) => isset($row['id'], $row['score']) && $current->has($row['id']))
                ->map(fn($row) => ['chunk' => $current->get($row['id']), 'score' => (float) $row['score']])
                ->values()->all();
        }

        $r = $this->embedResolved($query, $context, $cfg, $cred, $identity);
        if (! $this->resultMatches($r, $identity)) {
            return []; // A real-query outage cannot be compared to real vectors using mock.
        }
        // Provider calls can take a minute: re-read authorization and corpus.
        $chunks = $eligible($this->scopedChunks($institusiId, $opts));
        $scored = [];
        foreach ($chunks as $c) {
            $score = $this->cosine($r['embedding'], $c->embedding);
            if ($score >= $minScore) {
                $scored[] = ['chunk' => $c, 'score' => $score];
            }
        }

        usort($scored, fn($a, $b) => ($b['score'] <=> $a['score']) ?: ($a['chunk']->id <=> $b['chunk']->id));
        $scored = array_slice($scored, 0, $topK);
        if ($ttl > 0) {
            $this->writeCache($cacheKey($chunks), array_map(fn($row) => [
                'id' => $row['chunk']->id,
                'score' => $row['score'],
            ], $scored), $ttl);
        }

        return $scored;
    }

    private function scopedChunks(int $institusiId, array $opts): \Illuminate\Database\Eloquent\Collection
    {
        return DokumenChunk::query()
            ->with('dokumen')
            ->whereNotNull('embedding')
            ->whereNotNull('embedding_identity') // Legacy rows require reindexing.
            ->when(isset($opts['dokumen_id']), fn($q) => $q->where('dokumen_id', $opts['dokumen_id']))
            ->when(array_key_exists('dokumen_ids', $opts), fn($q) => $q->whereIn('dokumen_id', (array) $opts['dokumen_ids']))
            ->whereHas('dokumen', fn($q) => $q
                ->where(fn($qq) => $qq->where('institusi_id', $institusiId)->orWhereNull('institusi_id'))
                ->when($opts['sumber_konten'] ?? false, fn($qq) => $qq->where('sumber_konten', true)))
            ->orderBy('id')
            ->get();
    }

    private function chunkIdentity(array $identity, string $text): array
    {
        $identity['text_hash'] = hash('sha256', $text);
        $identity['signature'] = $this->hash($identity);

        return $identity;
    }

    private function chunkMatches(DokumenChunk $chunk, array $identity): bool
    {
        $stored = $chunk->embedding_identity;
        if (! is_array($stored) || ! $this->validVector($chunk->embedding, $identity['dimensions'])) {
            return false;
        }
        // Compare fields rather than JSON order (MySQL can reorder object keys).
        foreach ($this->chunkIdentity($identity, $chunk->teks) as $key => $value) {
            if (! array_key_exists($key, $stored) || $stored[$key] !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Kemiripan kosinus dua vektor (0..1 untuk vektor non-negatif; -1..1 umum).
     */
    public function cosine(array $a, array $b): float
    {
        $n = count($a);
        if (! $this->validVector($a, $n) || ! $this->validVector($b, $n)) {
            throw new InvalidArgumentException('Cosine membutuhkan vektor valid dengan dimensi yang sama.');
        }
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
        if ($provider === 'mock') {
            return null;
        }
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
