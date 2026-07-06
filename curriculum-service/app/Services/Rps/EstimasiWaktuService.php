<?php

namespace App\Services\Rps;

use App\Models\KonfigurasiAturan;
use App\Models\MataKuliah;

/**
 * Estimasi waktu belajar mingguan RPS — DETERMINISTIK dari SKS (Blueprint 7b/7.3).
 * AI/manusia TIDAK mengisi kolom ini; nilainya dihitung dari sks_teori/sks_praktik
 * dan aturan konversi SKS (KONFIGURASI_ATURAN jenis 'konversi_sks', tenant boleh override).
 *
 * Default SN-Dikti (menit per 1 SKS per minggu):
 *  - Teori: 50' tatap muka (TM) + 60' terstruktur (PT) + 60' mandiri (BM) = 170'
 *  - Praktikum: 170' praktik.
 */
class EstimasiWaktuService
{
    private const DEFAULT = [
        'teori_tatap_muka'  => 50,
        'teori_terstruktur' => 60,
        'teori_mandiri'     => 60,
        'praktik'           => 170,
    ];

    /**
     * Aturan konversi SKS untuk sebuah institusi (fallback ke default SN-Dikti).
     *
     * @return array{teori_tatap_muka:int,teori_terstruktur:int,teori_mandiri:int,praktik:int}
     */
    public function konversiUntuk(?int $institusiId): array
    {
        $nilai = KonfigurasiAturan::query()
            ->where('jenis_aturan', 'konversi_sks')
            ->where(fn($q) => $q->where('institusi_id', $institusiId)->orWhereNull('institusi_id'))
            ->orderByRaw('institusi_id IS NULL')
            ->value('nilai');

        $nilai = is_array($nilai) ? $nilai : [];

        return [
            'teori_tatap_muka'  => (int) ($nilai['teori_tatap_muka']  ?? self::DEFAULT['teori_tatap_muka']),
            'teori_terstruktur' => (int) ($nilai['teori_terstruktur'] ?? self::DEFAULT['teori_terstruktur']),
            'teori_mandiri'     => (int) ($nilai['teori_mandiri']     ?? self::DEFAULT['teori_mandiri']),
            'praktik'           => (int) ($nilai['praktik']           ?? self::DEFAULT['praktik']),
        ];
    }

    /**
     * Hitung estimasi waktu mingguan dari komponen SKS.
     *
     * @return array{tm_menit:int,pt_menit:int,bm_menit:int,praktik_menit:int,total_menit:int,teks:string}
     */
    public function hitung(int $sksTeori, int $sksPraktik, array $konversi): array
    {
        $k = array_merge(self::DEFAULT, array_intersect_key($konversi, self::DEFAULT));

        $tm      = $sksTeori   * (int) $k['teori_tatap_muka'];
        $pt      = $sksTeori   * (int) $k['teori_terstruktur'];
        $bm      = $sksTeori   * (int) $k['teori_mandiri'];
        $praktik = $sksPraktik * (int) $k['praktik'];
        $total   = $tm + $pt + $bm + $praktik;

        $bagian = [];
        if ($sksTeori > 0) {
            $bagian[] = "TM {$sksTeori}×{$k['teori_tatap_muka']} menit";
            $bagian[] = "PT {$sksTeori}×{$k['teori_terstruktur']} menit";
            $bagian[] = "BM {$sksTeori}×{$k['teori_mandiri']} menit";
        }
        if ($sksPraktik > 0) {
            $bagian[] = "Praktik {$sksPraktik}×{$k['praktik']} menit";
        }

        $teks = implode(', ', $bagian);
        if ($total > 0) {
            $teks .= " · Total {$total} menit/minggu";
        }

        return [
            'tm_menit'      => $tm,
            'pt_menit'      => $pt,
            'bm_menit'      => $bm,
            'praktik_menit' => $praktik,
            'total_menit'   => $total,
            'teks'          => $teks,
        ];
    }

    /**
     * Estimasi waktu mingguan untuk sebuah mata kuliah (memakai aturan tenantnya).
     *
     * @return array{tm_menit:int,pt_menit:int,bm_menit:int,praktik_menit:int,total_menit:int,teks:string}
     */
    public function untukMataKuliah(MataKuliah $mk): array
    {
        return $this->hitung(
            (int) $mk->sks_teori,
            (int) $mk->sks_praktik,
            $this->konversiUntuk($mk->institusi_id),
        );
    }
}
