<?php

namespace App\Services\Rps;

use App\Models\BahanKajian;
use App\Models\Cpl;
use App\Models\Cpmk;
use App\Models\CpmkCpl;
use App\Models\Dosen;
use App\Models\Institusi;
use App\Models\Kurikulum;
use App\Models\MataKuliah;
use App\Models\MkBahanKajian;
use App\Models\MkPengampu;
use App\Models\Referensi;
use App\Models\RpsVersion;
use App\Models\SubCpmk;

/**
 * Rangkum konteks MK untuk cetak/ekspor RPS sesuai format KPT 2024:
 * hierarki institusi, dosen pengampu, prasyarat, bahan kajian,
 * pustaka utama/pendukung, daftar CPMK & Sub-CPMK, matriks korelasi
 * Sub-CPMK × CPL.
 */
class RpsPrintContext
{
    public function build(RpsVersion $rps): array
    {
        $rps->loadMissing(['minggu.subCpmk', 'komponenPenilaian']);
        $institusiId = $rps->institusi_id;
        $kodeMk = $rps->kode_mk;

        // MK bisa berada di institusi berbeda dari RPS (hierarki tenant: RPS di
        // prodi, MK di fakultas, dst). Resolusi MK toleran, lalu kumpulkan semua
        // institusi kandidat (RPS + MK + kurikulum MK) agar data anak (pengampu,
        // pustaka, bahan kajian, CPMK/Sub-CPMK) yang tersimpan di institusi mana
        // pun dalam hierarki MK ini tetap terambil lengkap ke PDF/DOCX.
        $mk = MataKuliah::where('kode_mk', $kodeMk)->where('institusi_id', $institusiId)->first()
            ?? MataKuliah::where('kode_mk', $kodeMk)->first();
        $kurInstitusiId = $mk && $mk->kurikulum_id
            ? Kurikulum::whereKey($mk->kurikulum_id)->value('institusi_id')
            : null;
        $instIds = array_values(array_unique(array_filter([
            $institusiId,
            $mk?->institusi_id,
            $kurInstitusiId,
        ])));
        if ($instIds === []) {
            $instIds = [$institusiId];
        }

        // Hierarki Institusi (Prodi -> Fakultas -> Universitas), fallback bila 1 level.
        $prodi = null;
        $fakultas = null;
        $universitas = null;
        $inst = Institusi::find($institusiId);
        if ($inst) {
            $chain = [$inst];
            $cursor = $inst;
            while ($cursor->parent_id) {
                $cursor = Institusi::find($cursor->parent_id);
                if (! $cursor) {
                    break;
                }
                $chain[] = $cursor;
            }
            $prodi = $chain[0] ?? null;
            $fakultas = $chain[1] ?? null;
            $universitas = $chain[2] ?? $fakultas ?? $prodi;
        }

        // Dosen pengampu.
        $pengampu = MkPengampu::whereIn('institusi_id', $instIds)
            ->where('kode_mk', $kodeMk)
            ->get()
            ->map(function ($p) use ($instIds) {
                $dosen = Dosen::whereIn('institusi_id', $instIds)
                    ->where('nidn', $p->dosen_nidn)
                    ->first();
                return [
                    'nama'  => $dosen?->nama ?? $p->dosen_nidn,
                    'nidn'  => $p->dosen_nidn,
                    'peran' => $p->peran,
                ];
            })->values()->all();

        // Prasyarat.
        $prasyarat = null;
        if ($mk && $mk->prasyarat_kode) {
            $pm = MataKuliah::where('kode_mk', $mk->prasyarat_kode)
                ->whereIn('institusi_id', $instIds)
                ->first();
            $prasyarat = ['kode' => $mk->prasyarat_kode, 'nama' => $pm?->nama];
        }

        // Bahan kajian tertaut.
        $bahanKajian = MkBahanKajian::whereIn('institusi_id', $instIds)
            ->where('kode_mk', $kodeMk)
            ->with(['bahanKajian.keterampilan'])
            ->get()
            ->map(function ($mkbk) {
                $bk = $mkbk->bahanKajian;
                if (! $bk) {
                    return null;
                }
                return [
                    'nama'         => (string) ($bk->nama ?? ''),
                    'deskripsi'    => $bk->deskripsi,
                    'keterampilan' => $bk->keterampilan
                        ->map(fn($k) => (string) ($k->deskripsi ?? ''))
                        ->filter()
                        ->values()
                        ->all(),
                ];
            })
            ->filter()
            ->values()
            ->all();

        // Referensi.
        $refs = Referensi::whereIn('institusi_id', $instIds)
            ->where('kode_mk', $kodeMk)
            ->get();
        $pustakaUtama = $refs->where('tipe', 'utama')->pluck('sitasi')->values()->all();
        $pustakaPendukung = $refs->where('tipe', 'pendukung')->pluck('sitasi')->values()->all();
        if (empty($pustakaUtama) && empty($pustakaPendukung)) {
            $pustakaUtama = $refs->pluck('sitasi')->values()->all();
        }

        // Minggu per Sub-CPMK (dasar perhitungan kontribusi).
        $mingguPerSub = $rps->minggu
            ->filter(fn($m) => $m->sub_cpmk_id)
            ->groupBy('sub_cpmk_id')
            ->map(fn($g) => $g->count())
            ->toArray();
        $totalMingguAktif = array_sum($mingguPerSub);

        // CPMK & Sub-CPMK — ISOLASI VERSI: hanya yang BENAR-BENAR dipakai versi
        // ini (dirujuk rps_minggu / komponen_penilaian). Sisa generate lama yang
        // sudah dihapus/tak tersemat TIDAK ikut, dan isi versi lain tidak bocor
        // ke versi ini karena cpmk/sub_cpmk berbagi tabel per kode_mk.
        $subIdsDipakai = $rps->minggu->pluck('sub_cpmk_id')
            ->merge($rps->komponenPenilaian->pluck('sub_cpmk_id'))
            ->filter()
            ->unique()
            ->values();

        $subCpmkRaw = SubCpmk::whereIn('id', $subIdsDipakai)
            ->with('cpmk')
            ->orderBy('kode')
            ->get();

        $cpmkIds = $subCpmkRaw->pluck('cpmk_id')->filter()->unique()->values();

        // Kontribusi per CPMK (akumulasi minggu Sub-CPMK di bawahnya / total minggu aktif).
        $cpmkKontribusiMap = $subCpmkRaw
            ->groupBy(fn($s) => $s->cpmk?->kode ?? '?')
            ->map(function ($subs) use ($mingguPerSub, $totalMingguAktif) {
                $n = $subs->sum(fn($s) => $mingguPerSub[$s->id] ?? 0);
                return $totalMingguAktif > 0 ? round($n / $totalMingguAktif * 100, 2) : 0.0;
            });

        $cpmkList = Cpmk::whereIn('id', $cpmkIds)
            ->orderBy('kode')
            ->get()
            ->map(fn($c) => [
                'kode'              => $c->kode,
                'deskripsi'         => $c->deskripsi,
                'bloom'             => $this->bloomTag($c->taksonomi_kode),
                'kontribusi_persen' => (float) ($cpmkKontribusiMap[$c->kode] ?? 0),
            ])->values()->all();

        $subCpmkList = $subCpmkRaw
            ->map(fn($s) => [
                'kode'              => $s->kode,
                'deskripsi'         => $s->deskripsi,
                'cpmk'              => $s->cpmk?->kode,
                'bloom'             => $this->bloomTag($s->taksonomi_kode),
                'kontribusi_persen' => $totalMingguAktif > 0 ? round(($mingguPerSub[$s->id] ?? 0) / $totalMingguAktif * 100, 2) : 0.0,
            ])->values()->all();

        // Matriks CPL × Sub-CPMK (Sub-CPMK inherit bobot CPMK-nya).
        $ccRows = CpmkCpl::whereIn('cpmk_id', $cpmkIds)->get();
        $cplIds = $ccRows->pluck('cpl_id')->unique()->values();
        $cplList = Cpl::whereIn('id', $cplIds)->orderBy('kode')->get(['id', 'kode', 'deskripsi']);
        $cpmkToCpl = [];
        foreach ($ccRows as $r) {
            $cpmkToCpl[$r->cpmk_id][$r->cpl_id] = (float) ($r->bobot ?? 0);
        }
        $matriks = [
            'cpl'               => $cplList->map(fn($c) => ['id' => $c->id, 'kode' => $c->kode])->values()->all(),
            'total_minggu'      => $totalMingguAktif,
            'baris'             => $subCpmkRaw->map(function ($s) use ($cpmkToCpl, $cplList, $mingguPerSub, $totalMingguAktif) {
                $bobotPerCpl = [];
                foreach ($cplList as $c) {
                    $bobotPerCpl[$c->kode] = $cpmkToCpl[$s->cpmk_id][$c->id] ?? null;
                }
                $jumlahMinggu = $mingguPerSub[$s->id] ?? 0;
                return [
                    'sub_cpmk'          => $s->kode,
                    'cpmk'              => $s->cpmk?->kode,
                    'bobot_per_cpl'     => $bobotPerCpl,
                    'bobot_nilai'       => $s->bobot_persen !== null ? (float) $s->bobot_persen : null,
                    'jumlah_minggu'     => $jumlahMinggu,
                    'kontribusi_persen' => $totalMingguAktif > 0 ? round($jumlahMinggu / $totalMingguAktif * 100, 2) : 0.0,
                ];
            })->values()->all(),
        ];
        // Rekap kontribusi per CPMK (grup Sub-CPMK yg sudah dipakai di RPS).
        $groupByCpmk = $subCpmkRaw->groupBy(fn($s) => $s->cpmk?->kode ?? '?');
        $matriks['cpmk_kontribusi'] = $groupByCpmk->map(function ($subs, $kode) use ($mingguPerSub, $totalMingguAktif) {
            $n = $subs->sum(fn($s) => $mingguPerSub[$s->id] ?? 0);
            return [
                'cpmk'              => (string) $kode,
                'jumlah_minggu'     => $n,
                'kontribusi_persen' => $totalMingguAktif > 0 ? round($n / $totalMingguAktif * 100, 2) : 0.0,
            ];
        })->values()->all();

        return [
            'universitas'       => $universitas ? ['nama' => $universitas->nama] : null,
            'fakultas'          => $fakultas ? ['nama' => $fakultas->nama] : null,
            'prodi'             => $prodi ? ['nama' => $prodi->nama, 'kode' => $mk?->prodi_kode] : null,
            'pengampu'          => $pengampu,
            'prasyarat'         => $prasyarat,
            'bahan_kajian'      => $bahanKajian,
            'pustaka_utama'     => $pustakaUtama,
            'pustaka_pendukung' => $pustakaPendukung,
            'cpmk_list'         => $cpmkList,
            'sub_cpmk_list'     => $subCpmkList,
            'matriks_korelasi'  => $matriks,
        ];
    }

    public function bloomTag($kode): ?string
    {
        if ($kode === null || $kode === '') {
            return null;
        }
        $items = is_array($kode) ? $kode : [$kode];
        $items = array_values(array_filter(array_map('strval', $items), fn($v) => $v !== ''));
        return empty($items) ? null : '[' . implode(',', $items) . ']';
    }

    /**
     * Bentuk teks estimasi waktu selalu dalam satuan "menit" (tanpa tanda prime `′`).
     * Menerima array output EstimasiWaktuService atau string mentah.
     */
    public function formatEstimasi($waktu): string
    {
        if (! is_array($waktu)) {
            return trim((string) $waktu);
        }
        $bagian = [];
        $tm = (int) ($waktu['tm_menit'] ?? 0);
        $pt = (int) ($waktu['pt_menit'] ?? 0);
        $bm = (int) ($waktu['bm_menit'] ?? 0);
        $pr = (int) ($waktu['praktik_menit'] ?? 0);
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
        $total = (int) ($waktu['total_menit'] ?? ($tm + $pt + $bm + $pr));
        $teks = implode(', ', $bagian);
        if ($total > 0) {
            $teks .= ($teks !== '' ? ' · ' : '') . "Total {$total} menit/minggu";
        }
        $pertemuan = (int) ($waktu['jumlah_pertemuan'] ?? 0);
        if ($pertemuan > 0) {
            $teks .= ($teks !== '' ? ' · ' : '') . "{$pertemuan} pertemuan/minggu";
        }
        if ($teks === '') {
            // Fallback: bersihkan prime `′` dari teks lama.
            $teks = trim((string) ($waktu['teks'] ?? ''));
            $teks = str_replace(['′', "'"], ' menit', $teks);
        }
        return $teks;
    }

    /**
     * Normalisasi teks kriteria & teknik penilaian ke dua baris (Kriteria, lalu Teknik).
     */
    public function formatKriteria(?string $teks): string
    {
        $t = trim((string) $teks);
        if ($t === '') {
            return '';
        }
        // Sisipkan newline sebelum "Teknik:" / "Bentuk:" bila belum ada.
        $t = preg_replace('/\s*(?:\.\s+|;\s+|\s+)(?=(Teknik|Bentuk)\s*:)/u', "\n", $t);
        return trim($t);
    }
}
