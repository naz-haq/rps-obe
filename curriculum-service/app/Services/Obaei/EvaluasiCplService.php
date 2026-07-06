<?php

namespace App\Services\Obaei;

use App\Models\CapaianMahasiswa;
use App\Models\Cpl;
use App\Models\EvaluasiCpl;
use App\Models\TargetCpl;
use App\Services\Ai\AiService;

/**
 * Modul 6 — OBAEI (evaluasi ketercapaian CPL & tindak lanjut).
 *
 * Menutup siklus OBC→OBLT→OBAEI: capaian mahasiswa agregat dibandingkan dengan
 * TARGET_CPL (ambang + % target) untuk menentukan status ketercapaian tiap CPL.
 * Perhitungan deterministik; ringkasan naratif + rekomendasi bisa dilengkapi AI.
 */
class EvaluasiCplService
{
    /**
     * Agregasi ketercapaian tiap CPL untuk satu institusi (opsional per angkatan).
     *
     * @return array<int,array<string,mixed>>
     */
    public function agregasi(int $institusiId, ?string $angkatan = null, ?int $kurikulumId = null): array
    {
        $cplList = Cpl::query()
            ->where('institusi_id', $institusiId)
            ->when($kurikulumId, fn($q) => $q->where('kurikulum_id', $kurikulumId))
            ->with(['cpmk:id', 'target'])
            ->orderBy('kode')
            ->get();

        // Peta sub_cpmk_id → cpmk_id untuk menautkan capaian berbasis Sub-CPMK.
        $subToCpmk = \App\Models\SubCpmk::query()
            ->whereNotNull('cpmk_id')
            ->pluck('cpmk_id', 'id');

        $capaian = CapaianMahasiswa::query()
            ->where('institusi_id', $institusiId)
            ->when($angkatan, fn($q) => $q->where('angkatan', $angkatan))
            ->get();

        $hasil = [];

        foreach ($cplList as $cpl) {
            $cpmkIds = $cpl->cpmk->pluck('id')->map(fn($v) => (int) $v)->all();

            $terkait = $capaian->filter(function (CapaianMahasiswa $c) use ($cpmkIds, $subToCpmk) {
                $cpmkId = $c->cpmk_id;
                if ($cpmkId === null && $c->sub_cpmk_id !== null) {
                    $cpmkId = $subToCpmk[$c->sub_cpmk_id] ?? null;
                }

                return $cpmkId !== null && in_array((int) $cpmkId, $cpmkIds, true);
            });

            $target = $this->targetTerpilih($cpl->target, $angkatan);

            [$capaianAktual, $totalMhs] = $this->rerataTertimbang($terkait);

            $status = $this->tentukanStatus($terkait->isEmpty(), $target, $capaianAktual);

            $hasil[] = [
                'cpl_id'            => $cpl->id,
                'kode'             => $cpl->kode,
                'deskripsi'        => $cpl->deskripsi,
                'target_persen'    => $target?->persentase_target !== null ? (float) $target->persentase_target : null,
                'ambang_nilai'     => $target?->ambang_nilai !== null ? (float) $target->ambang_nilai : null,
                'capaian_persen'   => $terkait->isEmpty() ? null : round($capaianAktual, 2),
                'jumlah_mahasiswa' => $totalMhs,
                'jumlah_komponen'  => $terkait->count(),
                'status'           => $status,
                'selisih'          => ($target && ! $terkait->isEmpty())
                    ? round($capaianAktual - (float) $target->persentase_target, 2)
                    : null,
            ];
        }

        return $hasil;
    }

    /** Ringkasan angka untuk kartu dashboard. */
    public function ringkasan(array $agregasi): array
    {
        $total = count($agregasi);
        $tercapai = 0;
        $belum = 0;
        $tanpaData = 0;

        foreach ($agregasi as $row) {
            match ($row['status']) {
                'tercapai'   => $tercapai++,
                'belum'      => $belum++,
                default      => $tanpaData++,
            };
        }

        return [
            'total_cpl'   => $total,
            'tercapai'    => $tercapai,
            'belum'       => $belum,
            'tanpa_data'  => $tanpaData,
            'persen_tercapai' => $total > 0 ? round($tercapai / $total * 100, 1) : 0,
        ];
    }

    /** Pilih target sesuai angkatan; fallback ke target terbaru bila tak ada yang cocok. */
    private function targetTerpilih($targets, ?string $angkatan): ?TargetCpl
    {
        if ($targets->isEmpty()) {
            return null;
        }
        if ($angkatan) {
            $cocok = $targets->firstWhere('angkatan', $angkatan);
            if ($cocok) {
                return $cocok;
            }
        }

        return $targets->sortByDesc('id')->first();
    }

    /** Rerata persentase capaian tertimbang jumlah mahasiswa. */
    private function rerataTertimbang($capaian): array
    {
        $totalMhs = 0;
        $tertimbang = 0.0;
        $sederhana = 0.0;
        $n = 0;

        foreach ($capaian as $c) {
            $persen = $c->persentase_capaian_minimal !== null ? (float) $c->persentase_capaian_minimal : null;
            if ($persen === null) {
                continue;
            }
            $bobot = (int) ($c->jumlah_mahasiswa ?? 0);
            $totalMhs += $bobot;
            $tertimbang += $persen * $bobot;
            $sederhana += $persen;
            $n++;
        }

        if ($totalMhs > 0) {
            return [$tertimbang / $totalMhs, $totalMhs];
        }

        // Tanpa data jumlah mahasiswa → rerata sederhana.
        return [$n > 0 ? $sederhana / $n : 0.0, $totalMhs];
    }

    private function tentukanStatus(bool $kosong, ?TargetCpl $target, float $capaian): string
    {
        if ($kosong) {
            return 'tanpa_data';
        }
        if (! $target || $target->persentase_target === null) {
            return 'tanpa_target';
        }

        return $capaian + 1e-9 >= (float) $target->persentase_target ? 'tercapai' : 'belum';
    }

    /**
     * Lengkapi ringkasan naratif + usulan tindak lanjut satu evaluasi CPL via AI.
     * Mengembalikan {ringkasan, tindak_lanjut:[{catatan,prioritas}]}.
     */
    public function analisisAi(EvaluasiCpl $evaluasi, AiService $ai, ?string $angkatan = null): array
    {
        $evaluasi->loadMissing('cpl');
        $cpl = $evaluasi->cpl;

        $agregasi = collect($this->agregasi($evaluasi->institusi_id, $angkatan))
            ->firstWhere('cpl_id', $evaluasi->cpl_id);

        $system = 'Anda ahli penjaminan mutu OBE & akreditasi (SN-Dikti/LAM). Berdasar data ketercapaian CPL, '
            . 'tulis ringkasan naratif evaluasi yang objektif dan usulkan tindak lanjut konkret yang bisa mengalir '
            . 'ke perbaikan RPS siklus berikutnya. Balas HANYA JSON valid sesuai skema, tanpa teks lain.';

        $prompt = 'CPL: ' . ($cpl?->kode ?? '-') . ' — ' . ($cpl?->deskripsi ?? '') . "\n"
            . 'DATA KETERCAPAIAN: ' . json_encode($agregasi, JSON_UNESCAPED_UNICODE) . "\n\n"
            . 'Balas HANYA JSON: {"ringkasan":"..","tindak_lanjut":[{"catatan":"..","prioritas":"tinggi|sedang|rendah"}]}';

        $outcome = $ai->run('generate', $system, $prompt, ['institusi_id' => $evaluasi->institusi_id]);
        $data = $this->parseJson($outcome->failed() ? '' : $outcome->text());

        return [
            'ringkasan'     => is_string($data['ringkasan'] ?? null) ? $data['ringkasan'] : null,
            'tindak_lanjut' => is_array($data['tindak_lanjut'] ?? null) ? $data['tindak_lanjut'] : [],
        ];
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
