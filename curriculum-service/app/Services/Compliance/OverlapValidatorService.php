<?php

namespace App\Services\Compliance;

use App\Models\ValidasiOverlap;
use App\Services\Ai\AiService;
use Illuminate\Support\Facades\DB;

/**
 * Modul 3 — Validator Overlap (Blueprint 6, Fase 2).
 *
 * Mendeteksi KETERAMPILAN (butir bahan kajian) yang diklaim oleh lebih dari
 * satu mata kuliah lewat pivot MK_KETERAMPILAN, lalu mencatat/memperbarui
 * temuan di VALIDASI_OVERLAP. Analisis kesesuaian + rekomendasi bisa
 * dilengkapi AI secara terpisah agar hemat biaya (deteksi = deterministik).
 */
class OverlapValidatorService
{
    /**
     * Pindai overlap untuk satu institusi (opsional dibatasi satu kurikulum).
     * Deterministik: keterampilan dengan >1 kode_mk berbeda = overlap.
     *
     * @return array{diperiksa:int,overlap:int,baru:int,dibersihkan:int}
     */
    public function pindai(int $institusiId, ?int $kurikulumId = null): array
    {
        $rows = DB::table('mk_keterampilan as mk')
            ->join('keterampilan as k', 'k.id', '=', 'mk.keterampilan_id')
            ->join('bahan_kajian as bk', 'bk.id', '=', 'k.bahan_kajian_id')
            ->where('mk.institusi_id', $institusiId)
            ->when($kurikulumId, fn($q) => $q->where('bk.kurikulum_id', $kurikulumId))
            ->select('mk.keterampilan_id', 'mk.kode_mk', 'mk.fokus_spesifik')
            ->get()
            ->groupBy('keterampilan_id');

        $overlapKeterampilan = [];
        $baru = 0;

        foreach ($rows as $keterampilanId => $klaim) {
            // Klaim unik per kode_mk (satu MK boleh mengklaim keterampilan sama sekali saja).
            $perMk = $klaim
                ->groupBy('kode_mk')
                ->map(fn($g) => [
                    'kode_mk'        => $g->first()->kode_mk,
                    'fokus_spesifik' => $g->first()->fokus_spesifik,
                ])
                ->values();

            if ($perMk->count() < 2) {
                continue; // hanya 1 MK → bukan overlap
            }

            $overlapKeterampilan[] = (int) $keterampilanId;
            $mkTerlibat = $perMk->all();

            $record = ValidasiOverlap::firstOrNew([
                'institusi_id'   => $institusiId,
                'keterampilan_id' => (int) $keterampilanId,
            ]);

            $record->mk_terlibat = $mkTerlibat;
            $record->analisis = $this->analisisDeterministik($perMk->pluck('kode_mk')->all());

            // Status hanya diset saat baru; temuan yang sudah ditinjau manusia
            // (mis. 'aman') tidak ditimpa oleh pemindaian ulang.
            if (! $record->exists) {
                $record->status = 'perlu_review';
                $baru++;
            }

            $record->save();
        }

        // Bersihkan temuan lama yang tak lagi overlap dalam cakupan pindaian ini.
        $dibersihkan = $this->bersihkanUsang($institusiId, $kurikulumId, $overlapKeterampilan);

        return [
            'diperiksa'    => $rows->count(),
            'overlap'      => count($overlapKeterampilan),
            'baru'         => $baru,
            'dibersihkan'  => $dibersihkan,
        ];
    }

    /**
     * Hapus baris VALIDASI_OVERLAP dalam cakupan yang keterampilannya sudah
     * tidak overlap lagi (mis. klaim salah satu MK dicabut).
     */
    private function bersihkanUsang(int $institusiId, ?int $kurikulumId, array $masihOverlap): int
    {
        return ValidasiOverlap::where('institusi_id', $institusiId)
            ->when($kurikulumId, function ($q) use ($kurikulumId) {
                $q->whereIn('keterampilan_id', function ($sub) use ($kurikulumId) {
                    $sub->select('k.id')
                        ->from('keterampilan as k')
                        ->join('bahan_kajian as bk', 'bk.id', '=', 'k.bahan_kajian_id')
                        ->where('bk.kurikulum_id', $kurikulumId);
                });
            })
            ->when($masihOverlap !== [], fn($q) => $q->whereNotIn('keterampilan_id', $masihOverlap))
            ->delete();
    }

    /** Ringkasan deterministik daftar MK yang terlibat. */
    private function analisisDeterministik(array $kodeMk): string
    {
        $daftar = implode(', ', $kodeMk);

        return 'Keterampilan ini diklaim oleh ' . count($kodeMk) . ' mata kuliah (' . $daftar . '). '
            . 'Perlu ditinjau apakah pengulangan disengaja (penguatan bertingkat) atau tumpang tindih yang harus dipisahkan fokusnya.';
    }

    /**
     * Lengkapi analisis + rekomendasi satu temuan overlap dengan AI.
     * Mengembalikan temuan yang sudah diperbarui.
     */
    public function analisisAi(ValidasiOverlap $overlap, AiService $ai): ValidasiOverlap
    {
        $overlap->loadMissing('keterampilan.bahanKajian');
        $keterampilan = $overlap->keterampilan;

        $system = 'Anda ahli desain kurikulum OBE & SN-Dikti. Anda menilai apakah sebuah keterampilan (butir bahan kajian) '
            . 'yang diklaim oleh beberapa mata kuliah merupakan pengulangan yang WAJAR (penguatan bertingkat / scaffolding pada level '
            . 'taksonomi berbeda) atau TUMPANG TINDIH yang perlu dipisahkan agar tidak boros SKS. '
            . 'Berikan analisis ringkas dan rekomendasi konkret. Balas HANYA JSON valid sesuai skema, tanpa teks lain.';

        $mk = collect($overlap->mk_terlibat ?? [])
            ->map(fn($m) => ['kode_mk' => $m['kode_mk'] ?? '', 'fokus' => $m['fokus_spesifik'] ?? null]);

        $prompt = 'KETERAMPILAN: ' . ($keterampilan?->deskripsi ?? '(tanpa deskripsi)') . "\n"
            . 'BAHAN KAJIAN: ' . ($keterampilan?->bahanKajian?->nama ?? '-') . "\n"
            . "DIKLAIM OLEH MATA KULIAH:\n" . json_encode($mk, JSON_UNESCAPED_UNICODE) . "\n\n"
            . 'Balas HANYA JSON: {"status":"overlap|aman|perlu_review","analisis":"..","rekomendasi":".."}';

        $outcome = $ai->run('generate', $system, $prompt, ['institusi_id' => $overlap->institusi_id]);

        $data = $this->parseJson($outcome->failed() ? '' : $outcome->text());

        $status = $data['status'] ?? null;
        $overlap->analisis = $data['analisis'] ?? $overlap->analisis;
        $overlap->rekomendasi = $data['rekomendasi'] ?? $overlap->rekomendasi;
        if (in_array($status, ['overlap', 'aman', 'perlu_review'], true)) {
            $overlap->status = $status;
        }
        $overlap->save();

        return $overlap;
    }

    /** Ekstrak objek JSON dari keluaran AI (toleran blok kode/teks pembungkus). */
    private function parseJson(string $text): array
    {
        $clean = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text)));
        $data = json_decode($clean, true);
        if (! is_array($data)) {
            $start = strpos($clean, '{');
            $end = strrpos($clean, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $data = json_decode(substr($clean, $start, $end - $start + 1), true);
            }
        }

        return is_array($data) ? $data : [];
    }
}
