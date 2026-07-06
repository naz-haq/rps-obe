<?php

namespace App\Services\Ai;

use App\Models\AiValidasi;
use App\Models\DokumenChunk;
use InvalidArgumentException;

/**
 * Validator anti-halusinasi (Blueprint mode 5 + grounding ketat 7.5).
 *
 * Alur per klaim:
 *  1. Retrieval bukti dari DOKUMEN_CHUNK via EmbeddingService::search (RAG).
 *  2. Tanpa bukti memadai -> guardrail DETERMINISTIK 'tak_didukung' (tanpa LLM).
 *  3. Ada bukti -> penilaian LLM 'validator' (WAJIB lintas-provider dari
 *     generator, dijaga config/ai.php cross_provider_of) menilai grounded/
 *     tak_didukung/kontradiktif + skor + konteks pengganti.
 *  4. Klaim kategori KETAT (config strict_categories) yang tak grounded ->
 *     tindakan 'tolak' (tak boleh dikomit). Non-ketat -> 'revisi_ulang'.
 *  5. Setiap klaim dicatat satu baris ke AI_VALIDASI.
 */
class GroundingValidator
{
    public function __construct(
        private AiService $ai,
        private EmbeddingService $embeddings,
        private PromptRepository $prompts,
    ) {}

    /**
     * @param  array  $context  institusi_id (wajib), ai_interaksi_id (wajib),
     *   klaim (opsional [{teks,kategori}]; jika kosong diekstrak dari $teks),
     *   user_id, top_k, min_score
     * @return array{lolos:bool, hasil:array<int,array>, ditolak:array<int,string>}
     */
    public function validate(string $teks, array $context = []): array
    {
        $institusiId = $context['institusi_id'] ?? null;
        $interaksiId = $context['ai_interaksi_id'] ?? null;
        if (! $institusiId || ! $interaksiId) {
            throw new InvalidArgumentException('validate() butuh context institusi_id dan ai_interaksi_id.');
        }

        $klaimList = $context['klaim'] ?? $this->extractClaims($teks, $context);

        $cfg = (array) config('ai.grounding', []);
        $topK = (int) ($context['top_k'] ?? $cfg['top_k'] ?? 5);
        $minScore = (float) ($context['min_score'] ?? $cfg['min_score'] ?? 0.75);
        $strict = (array) config('ai.strict_categories', []);

        $hasil = [];
        $ditolak = [];

        foreach ($klaimList as $klaim) {
            $teksKlaim = is_array($klaim) ? (string) ($klaim['teks'] ?? '') : (string) $klaim;
            $kategori = is_array($klaim) ? ($klaim['kategori'] ?? null) : null;
            if (trim($teksKlaim) === '') {
                continue;
            }

            $hits = $this->embeddings->search($institusiId, $teksKlaim, $topK, ['min_score' => $minScore]);

            if ($hits === []) {
                // Guardrail: tanpa bukti -> tak didukung, tanpa memanggil LLM.
                $status = 'tak_didukung';
                $skor = 0.0;
                $konteks = null;
                $buktiIds = [];
            } else {
                $judgment = $this->judge($teksKlaim, $hits, $context);
                $status = $judgment['status'];
                $skor = $judgment['skor'];
                $konteks = $judgment['konteks'];
                $buktiIds = $judgment['bukti_ids'];
            }

            $isStrict = $kategori !== null && in_array($kategori, $strict, true);
            $tindakan = $this->decideTindakan($isStrict, $status);

            $validasi = AiValidasi::create([
                'ai_interaksi_id'   => $interaksiId,
                'klaim'             => $teksKlaim,
                'status'            => $status,
                'bukti_chunk_ids'   => $buktiIds,
                'skor_grounding'    => $skor,
                'konteks_pengganti' => $konteks,
                'tindakan'          => $tindakan,
            ]);

            if ($tindakan === 'tolak') {
                $ditolak[] = $teksKlaim;
            }

            $hasil[] = [
                'klaim'       => $teksKlaim,
                'kategori'    => $kategori,
                'status'      => $status,
                'skor'        => $skor,
                'tindakan'    => $tindakan,
                'konteks'     => $konteks,
                'bukti'       => $buktiIds,
                'validasi_id' => $validasi->id,
            ];
        }

        return ['lolos' => $ditolak === [], 'hasil' => $hasil, 'ditolak' => $ditolak];
    }

    /**
     * Keputusan tindakan atas status grounding & kekakuan kategori.
     */
    private function decideTindakan(bool $isStrict, string $status): string
    {
        if ($status === 'grounded') {
            return 'terima';
        }
        if ($status === 'kontradiktif') {
            return 'tolak';
        }

        // tak_didukung
        return $isStrict ? 'tolak' : 'revisi_ulang';
    }

    /**
     * Ekstrak klaim atomik dari teks via LLM ringan (task 'ekstraksi').
     *
     * @return array<int,array{teks:string,kategori:?string}>
     */
    private function extractClaims(string $teks, array $context): array
    {
        $prompt = $this->prompts->resolve('ekstraksi', $context['institusi_id'] ?? null);
        $system = $prompt['system'];
        $schema = $prompt['schema'];
        $body = "TEKS:\n{$teks}\n\nBalas HANYA JSON valid dengan struktur berikut:\n{$schema}";

        $outcome = $this->ai->run('ekstraksi', $system, $body, [
            'institusi_id' => $context['institusi_id'] ?? null,
            'user_id'      => $context['user_id'] ?? null,
            'mode'         => 'validate:ekstraksi',
        ]);

        $data = json_decode($this->strip($outcome->text()), true);

        return is_array($data['klaim'] ?? null) ? $data['klaim'] : [];
    }

    /**
     * Penilaian grounding satu klaim terhadap bukti (LLM validator lintas-provider).
     *
     * @param  array<int,array{chunk:DokumenChunk,score:float}>  $hits
     * @return array{status:string, skor:float, konteks:?string, bukti_ids:array<int,int>}
     */
    private function judge(string $klaim, array $hits, array $context): array
    {
        $bukti = [];
        foreach ($hits as $i => $h) {
            $bukti[] = ($i + 1) . '. ' . $h['chunk']->teks;
        }

        $resolved = $this->prompts->resolve('validator', $context['institusi_id'] ?? null);
        $system = $resolved['system'];
        $schema = $resolved['schema'];
        $prompt = "KLAIM:\n{$klaim}\n\nBUKTI:\n" . implode("\n", $bukti)
            . "\n\nBalas HANYA JSON valid dengan struktur berikut:\n{$schema}";

        $outcome = $this->ai->run('validator', $system, $prompt, [
            'institusi_id' => $context['institusi_id'] ?? null,
            'user_id'      => $context['user_id'] ?? null,
            'mode'         => 'validate:grounding',
        ]);

        $data = json_decode($this->strip($outcome->text()), true) ?: [];

        $status = in_array($data['status'] ?? '', ['grounded', 'tak_didukung', 'kontradiktif'], true)
            ? $data['status']
            : 'tak_didukung';
        $skor = (float) ($data['skor_grounding'] ?? 0);
        $konteks = $data['konteks_pengganti'] ?? null;
        if ($konteks === '') {
            $konteks = null;
        }

        $buktiIds = [];
        foreach ((array) ($data['bukti_nomor'] ?? []) as $n) {
            $idx = (int) $n - 1;
            if (isset($hits[$idx])) {
                $buktiIds[] = $hits[$idx]['chunk']->id;
            }
        }

        return ['status' => $status, 'skor' => $skor, 'konteks' => $konteks, 'bukti_ids' => $buktiIds];
    }

    private function strip(string $t): string
    {
        $t = trim($t);
        $t = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $t);

        return trim((string) $t);
    }
}
