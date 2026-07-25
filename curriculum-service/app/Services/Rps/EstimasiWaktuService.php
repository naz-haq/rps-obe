<?php

namespace App\Services\Rps;

use App\Models\KonfigurasiAturan;
use App\Models\MataKuliah;

/**
 * Estimasi waktu belajar mingguan RPS — DETERMINISTIK dari SKS (Blueprint 7b/7.3).
 * AI/manusia TIDAK mengisi kolom ini; nilainya dihitung dari sks_teori/sks_praktik
 * dan aturan konversi SKS (KONFIGURASI_ATURAN, tenant boleh override tiap angka).
 *
 * Rujukan angka default:
 *  - Permendikbud No. 3/2020 (SN-Dikti): 1 SKS teori/pekan = 50' TM + 60' PT + 60' BM = 170';
 *    praktikum 170'/pekan. (jenis_aturan 'konversi_sks'). Selaras dgn Permendikbudristek
 *    53/2023 (1 SKS ≈ 45 jam/semester = 170' × 16 pekan).
 *
 * Mode distribusi beban (jenis_aturan 'mode_distribusi_waktu', per pola MK):
 *  - 'sebar'    (reguler): beban 170'/SKS disebar rata ~16 pekan.
 *  - 'padat'    (blok)   : total beban semester dipadatkan ke N pekan
 *                          (menit/pekan × minggu_efektif/N).
 *  - 'lapangan' (profesi/PKPA): beban dari jam kerja di wahana
 *                          (jam_per_hari × hari_per_minggu).
 * Jumlah pertemuan/pekan: default dihitung (kelas = ceil((TM+Praktik)/durasi_sesi);
 *  profesi = hari_per_minggu). MK boleh menimpa via kolom mata_kuliah.jumlah_pertemuan.
 */
class EstimasiWaktuService
{
    private const DEFAULT = [
        'teori_tatap_muka'  => 50,
        'teori_terstruktur' => 60,
        'teori_mandiri'     => 60,
        'praktik'           => 170,
    ];

    private const MINGGU_EFEKTIF_DEFAULT         = 16;
    private const MENIT_PER_SESI_DEFAULT         = 50;   // SN-Dikti tatap muka
    private const MINGGU_PER_SKS_PROFESI_DEFAULT = 1.0;
    private const PROFESI_JAM_PER_HARI_DEFAULT   = 8;
    private const PROFESI_HARI_PER_MINGGU_DEFAULT = 5;

    /**
     * Baca nilai (array) satu jenis aturan untuk institusi (fallback ke aturan global
     * institusi_id NULL). Kembalikan array kosong bila tak ada.
     *
     * @return array<string,mixed>
     */
    private function nilaiAturan(?int $institusiId, string $jenis): array
    {
        $nilai = KonfigurasiAturan::query()
            ->where('jenis_aturan', $jenis)
            ->where(fn($q) => $q->where('institusi_id', $institusiId)->orWhereNull('institusi_id'))
            ->orderByRaw('institusi_id IS NULL')
            ->value('nilai');

        return is_array($nilai) ? $nilai : [];
    }

    /**
     * Aturan konversi SKS untuk sebuah institusi (fallback ke default SN-Dikti).
     *
     * @return array{teori_tatap_muka:int,teori_terstruktur:int,teori_mandiri:int,praktik:int}
     */
    public function konversiUntuk(?int $institusiId): array
    {
        $nilai = $this->nilaiAturan($institusiId, 'konversi_sks');

        return [
            'teori_tatap_muka'  => (int) ($nilai['teori_tatap_muka']  ?? self::DEFAULT['teori_tatap_muka']),
            'teori_terstruktur' => (int) ($nilai['teori_terstruktur'] ?? self::DEFAULT['teori_terstruktur']),
            'teori_mandiri'     => (int) ($nilai['teori_mandiri']     ?? self::DEFAULT['teori_mandiri']),
            'praktik'           => (int) ($nilai['praktik']           ?? self::DEFAULT['praktik']),
        ];
    }

    /** Jumlah pekan efektif satu semester (default 16). */
    public function mingguEfektif(?int $institusiId): int
    {
        $v = $this->nilaiAturan($institusiId, 'jumlah_minggu')['minggu_efektif'] ?? null;

        return is_numeric($v) && (int) $v > 0 ? (int) $v : self::MINGGU_EFEKTIF_DEFAULT;
    }

    /** Durasi satu sesi/pertemuan dalam menit (default 50). */
    public function durasiSesi(?int $institusiId): int
    {
        $v = $this->nilaiAturan($institusiId, 'durasi_sesi')['menit_per_sesi'] ?? null;

        return is_numeric($v) && (int) $v > 0 ? (int) $v : self::MENIT_PER_SESI_DEFAULT;
    }

    /** Mode distribusi beban untuk sebuah pola MK (sebar/padat/lapangan). */
    public function modeDistribusi(?int $institusiId, string $pola): string
    {
        $map  = $this->nilaiAturan($institusiId, 'mode_distribusi_waktu');
        $mode = $map[$pola] ?? null;
        if (in_array($mode, ['sebar', 'padat', 'lapangan'], true)) {
            return $mode;
        }

        return match ($pola) {
            'blok'    => 'padat',
            'profesi' => 'lapangan',
            default   => 'sebar',
        };
    }

    /**
     * Jumlah pekan efektif MK: nilai eksplisit per-MK menimpa; jika kosong pakai
     * default global 'jumlah_minggu'. Pola 'profesi' tanpa nilai eksplisit → SKS × faktor
     * ('konversi_minggu_profesi'). Fakta kaku → sumber utamanya isian Kaprodi.
     */
    public function jumlahMingguUntuk(MataKuliah $mk): int
    {
        if (! empty($mk->jumlah_minggu) && (int) $mk->jumlah_minggu > 0) {
            return (int) $mk->jumlah_minggu;
        }

        if (($mk->pola ?: 'reguler') === 'profesi') {
            $dari = $this->mingguProfesiDariSks($mk);
            if ($dari > 0) {
                return $dari;
            }
        }

        return $this->mingguEfektif($mk->institusi_id);
    }

    /** Pekan MK profesi = ceil(total SKS × minggu_per_sks). */
    private function mingguProfesiDariSks(MataKuliah $mk): int
    {
        $faktor = $this->nilaiAturan($mk->institusi_id, 'konversi_minggu_profesi')['minggu_per_sks'] ?? null;
        $faktor = is_numeric($faktor) && (float) $faktor > 0 ? (float) $faktor : self::MINGGU_PER_SKS_PROFESI_DEFAULT;

        return (int) ceil(((int) $mk->sks_teori + (int) $mk->sks_praktik) * $faktor);
    }

    /**
     * Jadwal kerja wahana untuk MK profesi/PKPA (mode 'lapangan').
     *
     * @return array{jam_per_hari:float,hari_per_minggu:int}
     */
    private function jadwalProfesi(?int $institusiId): array
    {
        $n          = $this->nilaiAturan($institusiId, 'konversi_minggu_profesi');
        $jamHari    = $n['jam_per_hari'] ?? null;
        $hariMinggu = $n['hari_per_minggu'] ?? null;

        return [
            'jam_per_hari'    => is_numeric($jamHari) && (float) $jamHari > 0 ? (float) $jamHari : (float) self::PROFESI_JAM_PER_HARI_DEFAULT,
            'hari_per_minggu' => is_numeric($hariMinggu) && (int) $hariMinggu > 0 ? (int) $hariMinggu : self::PROFESI_HARI_PER_MINGGU_DEFAULT,
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
     * Estimasi waktu mingguan untuk sebuah mata kuliah (sadar pola & mode distribusi).
     * $jumlahMinggu boleh diisi bila sudah dihitung di pemanggil; jika null, di-resolve
     * dari aturan (mode 'padat' butuh nilai ini untuk memadatkan beban).
     *
     * @return array{tm_menit:int,pt_menit:int,bm_menit:int,praktik_menit:int,total_menit:int,jumlah_pertemuan:int,mode:string,teks:string}
     */
    public function untukMataKuliah(MataKuliah $mk, ?int $jumlahMinggu = null): array
    {
        $pola     = $mk->pola ?: 'reguler';
        $mode     = $this->modeDistribusi($mk->institusi_id, $pola);
        $minggu   = $jumlahMinggu !== null && $jumlahMinggu > 0 ? $jumlahMinggu : $this->jumlahMingguUntuk($mk);
        $override = ! empty($mk->jumlah_pertemuan) && (int) $mk->jumlah_pertemuan > 0 ? (int) $mk->jumlah_pertemuan : null;

        if ($mode === 'lapangan') {
            return $this->estimasiLapangan($mk, $mode, $override);
        }

        $base = $this->hitung(
            (int) $mk->sks_teori,
            (int) $mk->sks_praktik,
            $this->konversiUntuk($mk->institusi_id),
        );

        // Blok: total beban semester dipadatkan ke jumlah pekan aktual.
        if ($mode === 'padat') {
            $efektif = $this->mingguEfektif($mk->institusi_id);
            $faktor  = $minggu > 0 ? $efektif / $minggu : 1.0;
            $base    = $this->skalakan($base, $faktor);
        }

        return $this->lengkapiPertemuan($base, $mk->institusi_id, $mode, $override);
    }

    /** Skala komponen menit dengan faktor (untuk mode 'padat'). */
    private function skalakan(array $w, float $faktor): array
    {
        $tm = (int) round((int) ($w['tm_menit'] ?? 0) * $faktor);
        $pt = (int) round((int) ($w['pt_menit'] ?? 0) * $faktor);
        $bm = (int) round((int) ($w['bm_menit'] ?? 0) * $faktor);
        $pr = (int) round((int) ($w['praktik_menit'] ?? 0) * $faktor);

        return [
            'tm_menit'      => $tm,
            'pt_menit'      => $pt,
            'bm_menit'      => $bm,
            'praktik_menit' => $pr,
            'total_menit'   => $tm + $pt + $bm + $pr,
        ];
    }

    /** Lengkapi hasil dengan jumlah pertemuan/pekan (override menang) + teks + mode. */
    private function lengkapiPertemuan(array $w, ?int $institusiId, string $mode, ?int $override = null): array
    {
        $tm    = (int) ($w['tm_menit'] ?? 0);
        $pt    = (int) ($w['pt_menit'] ?? 0);
        $bm    = (int) ($w['bm_menit'] ?? 0);
        $pr    = (int) ($w['praktik_menit'] ?? 0);
        $total = (int) ($w['total_menit'] ?? ($tm + $pt + $bm + $pr));

        $durasi    = $this->durasiSesi($institusiId);
        $kontak    = $tm + $pr; // tatap muka + praktik = sesi terjadwal
        $derived   = $durasi > 0 && $kontak > 0 ? (int) ceil($kontak / $durasi) : 0;
        $pertemuan = $override ?? $derived;

        return [
            'tm_menit'         => $tm,
            'pt_menit'         => $pt,
            'bm_menit'         => $bm,
            'praktik_menit'    => $pr,
            'total_menit'      => $total,
            'jumlah_pertemuan' => $pertemuan,
            'mode'             => $mode,
            'teks'             => $this->teks($tm, $pt, $bm, $pr, $total, $pertemuan),
        ];
    }

    /** Estimasi mingguan MK profesi/PKPA dari jadwal kerja wahana (override hari menang). */
    private function estimasiLapangan(MataKuliah $mk, string $mode, ?int $override = null): array
    {
        $jadwal      = $this->jadwalProfesi($mk->institusi_id);
        $hari        = $override ?? (int) $jadwal['hari_per_minggu'];
        $menitMinggu = (int) round($jadwal['jam_per_hari'] * $hari * 60);
        $jam         = rtrim(rtrim(number_format($jadwal['jam_per_hari'], 1, '.', ''), '0'), '.');

        return [
            'tm_menit'         => 0,
            'pt_menit'         => 0,
            'bm_menit'         => 0,
            'praktik_menit'    => $menitMinggu,
            'total_menit'      => $menitMinggu,
            'jumlah_pertemuan' => $hari,
            'mode'             => $mode,
            'teks'             => "Praktik lapangan {$jam} jam × {$hari} hari · Total {$menitMinggu} menit/minggu · {$hari} hari/minggu",
        ];
    }

    /** Bentuk teks ringkas estimasi (menit) + jumlah pertemuan. */
    private function teks(int $tm, int $pt, int $bm, int $pr, int $total, int $pertemuan): string
    {
        $bagian = [];
        if ($tm > 0) {
            $bagian[] = "TM {$tm} menit";
        }
        if ($pt > 0) {
            $bagian[] = "PT {$pt} menit";
        }
        if ($bm > 0) {
            $bagian[] = "BM {$bm} menit";
        }
        if ($pr > 0) {
            $bagian[] = "Praktik {$pr} menit";
        }

        $teks = implode(', ', $bagian);
        if ($total > 0) {
            $teks .= ($teks !== '' ? ' · ' : '') . "Total {$total} menit/minggu";
        }
        if ($pertemuan > 0) {
            $teks .= " · {$pertemuan} pertemuan/minggu";
        }

        return $teks;
    }
}
