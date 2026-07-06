<?php

namespace App\Services\Rps;

use App\Services\Ai\AiService;
use App\Services\Ai\PromptRepository;

/**
 * Audit Keselarasan Konstruktif (Constructive Alignment) RPS berbasis AI
 * (fitur #6, mengadopsi rps-obe-builder). Menghasilkan skor 0-100 + status +
 * daftar isu (success/warning/error) dengan saran perbaikan.
 *
 * Melengkapi self-check deterministik di frontend (fitur #3): di sini AI menilai
 * kualitas taksonomi & kesepadanan asesmen yang tak bisa dicek dengan aturan saja.
 */
class RpsAuditService
{
    public function __construct(
        private AiService $ai,
        private PromptRepository $prompts,
    ) {}

    /**
     * @param  array  $snapshot  keluaran RpsSnapshot
     * @return array{skor_keseluruhan:int,status:string,umpan_balik:string,isu:array,sumber_prompt:string}
     */
    public function audit(array $snapshot, int $institusiId, ?string $jenisMk = null): array
    {
        $prompt = $this->prompts->resolve('audit', $institusiId, $jenisMk);

        $userPrompt = "Audit keselarasan konstruktif RPS berikut. Balas HANYA JSON sesuai skema:\n"
            . $prompt['schema'] . "\n\n"
            . "=== DATA RPS ===\n"
            . json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $outcome = $this->ai->run('generate', $prompt['system'], $userPrompt, [
            'institusi_id' => $institusiId,
            'mode'         => 'audit:keselarasan',
        ]);

        if ($outcome->failed()) {
            throw new \RuntimeException('Panggilan AI audit gagal: ' . ($outcome->result->error ?? 'tidak diketahui'));
        }

        $data = $this->parseJson($outcome->text());

        return [
            'skor_keseluruhan' => (int) ($data['skor_keseluruhan'] ?? 0),
            'status'           => (string) ($data['status'] ?? 'Tidak diketahui'),
            'umpan_balik'      => (string) ($data['umpan_balik'] ?? ''),
            'isu'              => array_map(fn($i) => [
                'tipe'       => $i['tipe'] ?? 'warning',
                'kategori'   => $i['kategori'] ?? 'Umum',
                'kode_target' => $i['kode_target'] ?? '',
                'pesan'      => $i['pesan'] ?? '',
                'saran'      => $i['saran'] ?? '',
            ], is_array($data['isu'] ?? null) ? $data['isu'] : []),
            'sumber_prompt'    => $prompt['sumber'],
        ];
    }

    private function parseJson(string $text): array
    {
        $text = trim($text);
        // Buang pagar kode ```json ... ``` bila ada.
        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```[a-zA-Z]*\s*|\s*```$/', '', $text) ?? $text;
        }
        // Ambil objek JSON pertama bila ada teks pembungkus.
        if (! str_starts_with($text, '{')) {
            $start = strpos($text, '{');
            $end = strrpos($text, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $text = substr($text, $start, $end - $start + 1);
            }
        }

        $data = json_decode($text, true);

        return is_array($data) ? $data : [];
    }
}
