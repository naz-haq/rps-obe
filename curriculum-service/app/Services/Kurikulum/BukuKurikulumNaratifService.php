<?php

namespace App\Services\Kurikulum;

use App\Models\Kurikulum;
use App\Services\Ai\AiService;
use App\Services\Ai\PromptRepository;
use Throwable;

/**
 * Narasi AI untuk Buku Kurikulum (OPSIONAL & fail-safe).
 *
 * Hanya menghasilkan bagian PROSA (kata pengantar, ringkasan profil lulusan,
 * rasional struktur MK) yang WAJIB berpijak pada data deterministik yang
 * diberikan. Semua angka/daftar tetap dari BukuKurikulumBuilder. Bila AI gagal
 * atau tak tersedia, mengembalikan array kosong sehingga dokumen tetap dirakit.
 */
class BukuKurikulumNaratifService
{
    public function __construct(
        private AiService $ai,
        private PromptRepository $prompts,
    ) {}

    /**
     * @param  array<string,mixed>  $buku
     * @return array<string,string>  peta bagian => prosa (kosong bila gagal)
     */
    public function generate(Kurikulum $kurikulum, array $buku): array
    {
        try {
            $cfg = $this->prompts->resolve('buku_naratif', $kurikulum->institusi_id);
            $system = $cfg['system'] ?? '';
            if ($system === '') {
                return [];
            }

            $prompt = $this->konteks($buku, $cfg['schema'] ?? '');

            $outcome = $this->ai->run('generate', $system, $prompt, [
                'institusi_id' => $kurikulum->institusi_id,
                'entity_type'  => 'Kurikulum',
                'entity_id'    => $kurikulum->id,
                'mode'         => 'generate:buku_naratif',
                'max_tokens'   => 3000,
            ]);

            if ($outcome->failed()) {
                return [];
            }

            $data = $this->parse($outcome->text());

            // Hanya ambil kunci prosa yang dikenal exporter, sebagai string.
            $out = [];
            foreach (['pengantar', 'profil_lulusan', 'cpl', 'mata_kuliah'] as $key) {
                $val = trim((string) ($data[$key] ?? ''));
                if ($val !== '') {
                    $out[$key] = $val;
                }
            }

            return $out;
        } catch (Throwable) {
            return [];
        }
    }

    /** Ringkasan deterministik sebagai konteks (AI hanya menarasikan ini). */
    private function konteks(array $buku, string $schema): string
    {
        $identitas = $buku['identitas'] ?? [];
        $ringkas = [
            'prodi'          => $identitas['prodi']['nama'] ?? null,
            'fakultas'       => $identitas['fakultas']['nama'] ?? null,
            'universitas'    => $identitas['universitas']['nama'] ?? null,
            'kurikulum'      => $identitas['kurikulum'] ?? [],
            'profil_lulusan' => $buku['profil_lulusan'] ?? [],
            'cpl'            => $buku['cpl'] ?? [],
            'jumlah_mk'      => array_sum(array_map(fn($s) => count($s['mata_kuliah'] ?? []), $buku['mata_kuliah'] ?? [])),
            'sebaran_semester' => array_map(
                fn($s) => ['semester' => $s['semester'] ?? null, 'jumlah_mk' => count($s['mata_kuliah'] ?? [])],
                $buku['mata_kuliah'] ?? []
            ),
        ];

        $bagian = [];
        $bagian[] = 'DATA KURIKULUM (satu-satunya sumber; JANGAN menambah fakta di luar ini):';
        $bagian[] = json_encode($ringkas, JSON_UNESCAPED_UNICODE);
        if ($schema !== '') {
            $bagian[] = "\nBalas HANYA JSON valid sesuai skema:";
            $bagian[] = $schema;
        }

        return implode("\n", $bagian);
    }

    /** @return array<string,mixed> */
    private function parse(string $text): array
    {
        $text = trim($text);
        // Buang pagar kode markdown bila ada.
        $text = preg_replace('/^```(?:json)?|```$/m', '', $text) ?? $text;
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end < $start) {
            return [];
        }
        $json = substr($text, $start, $end - $start + 1);
        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }
}
