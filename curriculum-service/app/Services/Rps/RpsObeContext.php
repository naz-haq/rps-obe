<?php

namespace App\Services\Rps;

use App\Models\Cpl;
use App\Models\CplBahanKajian;
use App\Models\Cpmk;
use App\Models\CpmkCpl;
use App\Models\Institusi;
use App\Models\Kurikulum;
use App\Models\MataKuliah;
use App\Models\MkBahanKajian;
use App\Models\MkCpl;
use App\Models\MkPengampu;
use App\Models\Referensi;
use App\Models\RpsVersion;
use App\Models\SubCpmk;
use App\Models\User;
use App\Services\Media\GambarService;
use Illuminate\Support\Collection;

/**
 * Konteks dokumen RPS format OBE (formulir mutu, bagian A–P). Semua turunan
 * (matriks, rekap asesmen, evaluasi, lampiran) dihitung dari data RPS; isian
 * manual pada `rps_version.kelengkapan` menimpa nilai turunan.
 */
class RpsObeContext
{
    /** Skala konversi bawaan bila institusi belum mengatur `skala_nilai`. */
    public const SKALA_NILAI_DEFAULT = [
        ['min' => 85, 'max' => 100, 'huruf' => 'A', 'keterangan' => 'Sangat Baik'],
        ['min' => 75, 'max' => 84.99, 'huruf' => 'B+', 'keterangan' => 'Baik Sekali'],
        ['min' => 70, 'max' => 74.99, 'huruf' => 'B', 'keterangan' => 'Baik'],
        ['min' => 65, 'max' => 69.99, 'huruf' => 'C+', 'keterangan' => 'Cukup Baik'],
        ['min' => 60, 'max' => 64.99, 'huruf' => 'C', 'keterangan' => 'Cukup'],
        ['min' => 50, 'max' => 59.99, 'huruf' => 'D', 'keterangan' => 'Kurang'],
        ['min' => 0, 'max' => 49.99, 'huruf' => 'E', 'keterangan' => 'Sangat Kurang'],
    ];

    /** Daftar lampiran instrumen (bagian P): key => [label, ketentuan]. */
    public const LAMPIRAN = [
        'kisi_kisi'           => ['Kisi-kisi dan instrumen UTS/UAS', 'Wajib jika menggunakan tes'],
        'rubrik_tugas'        => ['Rubrik tugas/studi kasus', 'Jika digunakan'],
        'rubrik_proyek'       => ['Rubrik proyek/PBL', 'Jika digunakan'],
        'checklist_praktikum' => ['Checklist/rubrik praktikum/unjuk kerja', 'Untuk praktikum/skill'],
        'lkm'                 => ['Lembar kerja mahasiswa (LKM/LKPD)', 'Jika digunakan'],
        'logbook'             => ['Logbook praktikum', 'Jika digunakan'],
        'refleksi'            => ['Instrumen refleksi', 'Jika reflective learning'],
        'lainnya'             => ['Instrumen lain sesuai karakteristik MK', 'Jika diperlukan'],
    ];

    /** Label & teknik baku per jenis komponen penilaian. */
    private const JENIS_ASESMEN = [
        'partisipasi'       => ['Aktivitas/Partisipasi', 'Observasi'],
        'aktivitas'         => ['Aktivitas/Partisipasi', 'Observasi'],
        'tugas'             => ['Tugas/Studi Kasus', 'Penilaian produk'],
        'studi_kasus'       => ['Tugas/Studi Kasus', 'Penilaian produk'],
        'proyek'            => ['Proyek/PBL', 'Penilaian produk & presentasi'],
        'presentasi'        => ['Presentasi', 'Unjuk kerja lisan'],
        'kuis'              => ['Kuis', 'Tes'],
        'laporan_praktikum' => ['Laporan Praktikum', 'Penilaian produk (laporan)'],
        'responsi'          => ['Responsi', 'Tes lisan/tulis'],
        'skill_assessment'  => ['Unjuk Kerja/Keterampilan', 'Observasi unjuk kerja'],
        'osce'              => ['OSCE/OSPE', 'Unjuk kerja terstruktur'],
        'uts'               => ['UTS', 'Tes/kinerja'],
        'uas'               => ['UAS', 'Tes/kinerja'],
    ];

    private const JENIS_REFERENSI = [
        'utama'     => 'Referensi Utama',
        'pendukung' => 'Referensi Tambahan',
        'standar'   => 'Standar/Guideline/Farmakope',
        'jurnal'    => 'Jurnal/Referensi mutakhir',
    ];

    public function __construct(
        private RpsPrintContext $print,
        private EstimasiWaktuService $estimasi,
        private GambarService $gambar,
    ) {}

    /** @param bool $denganBerkas sertakan path absolut logo/TTD (hanya untuk render cetak, jangan ke JSON). */
    public function build(RpsVersion $rps, bool $denganBerkas = false): array
    {
        $rps->loadMissing([
            'minggu.subCpmk.cpmk',
            'minggu.subCpmkSemua.cpmk',
            'komponenPenilaian.subCpmk.cpmk',
            'komponenPenilaian.rubrik.kriteria',
        ]);
        $base = $this->print->build($rps);
        $kel = is_array($rps->kelengkapan) ? $rps->kelengkapan : [];

        $mk = MataKuliah::where('kode_mk', $rps->kode_mk)->where('institusi_id', $rps->institusi_id)->first()
            ?? MataKuliah::where('kode_mk', $rps->kode_mk)->first();
        $prodi = Institusi::find($rps->institusi_id);
        $kurikulum = $mk?->kurikulum_id ? Kurikulum::find($mk->kurikulum_id) : null;
        $instIds = array_values(array_unique(array_filter([$rps->institusi_id, $mk?->institusi_id, $kurikulum?->institusi_id])));

        $minggu = $rps->minggu->sortBy('minggu_ke')->values();
        $komponen = $rps->komponenPenilaian->values();

        // Sub-CPMK & CPMK yang benar-benar dipakai versi ini (pekan + komponen).
        $subIds = $minggu->flatMap(fn($m) => $this->print->subIdsMinggu($m))
            ->merge($komponen->pluck('sub_cpmk_id'))
            ->filter()->unique()->values();
        $subs = SubCpmk::whereIn('id', $subIds)->with('cpmk')->get()
            ->sortBy('kode', SORT_NATURAL | SORT_FLAG_CASE)->values();
        $cpmks = Cpmk::whereIn('id', $subs->pluck('cpmk_id')->filter()->unique())->get()
            ->sortBy('kode', SORT_NATURAL | SORT_FLAG_CASE)->values();
        $cpmkKode = $cpmks->pluck('kode', 'id');

        $cpmkCpl = CpmkCpl::whereIn('cpmk_id', $cpmks->pluck('id'))->get();
        $cpls = Cpl::whereIn('id', $cpmkCpl->pluck('cpl_id')->unique())->get()
            ->sortBy('kode', SORT_NATURAL | SORT_FLAG_CASE)->values();
        $cplKode = $cpls->pluck('kode', 'id');
        $cplPerCpmk = [];
        $cpmkPerCpl = [];
        foreach ($cpmkCpl as $r) {
            if (isset($cplKode[$r->cpl_id])) {
                $cplPerCpmk[$r->cpmk_id][] = $cplKode[$r->cpl_id];
                $cpmkPerCpl[$r->cpl_id][] = $cpmkKode[$r->cpmk_id];
            }
        }

        $cpmkPerMinggu = fn($m) => collect($this->print->subIdsMinggu($m))
            ->map(fn($id) => $subs->firstWhere('id', $id)?->cpmk_id)
            ->filter()->unique()->map(fn($id) => $cpmkKode[$id] ?? null)->filter()->values()->all();

        $cpmkUjian = $this->cpmkPerUjian($minggu, $cpmkPerMinggu);
        $kelompok = $this->kelompokAsesmen($komponen, $cpmkUjian);
        $pengampu = $base['pengampu'] ?? [];
        $tanggalSusun = optional($rps->tanggal_penyusunan)->format('d-m-Y');

        return [
            'kop'              => [
                'universitas'    => $base['universitas']['nama'] ?? null,
                'fakultas'       => $base['fakultas']['nama'] ?? null,
                'prodi'          => $base['prodi']['nama'] ?? $prodi?->nama,
                'kode_dokumen'   => $rps->kode_dokumen,
                'tanggal'        => $tanggalSusun,
                'revisi'         => max(0, (int) $rps->versi - 1),
                'berlaku_mulai'  => optional($rps->berlaku_mulai ?? $rps->approved_at)->format('d-m-Y'),
                'tahun_akademik' => $rps->tahun_akademik,
                'status'         => $rps->pernahDisetujui() ? 'Final' : 'Draft',
                'logo_file'      => $denganBerkas ? $this->logoFile($rps->institusi_id) : null,
            ],
            'identitas'        => [
                'nama_mk'             => $mk?->nama ?? $rps->kode_mk,
                'kode_mk'             => $rps->kode_mk,
                'prodi'               => $base['prodi']['nama'] ?? $prodi?->nama,
                'jenjang'             => $prodi?->jenjang,
                'bidang_keahlian'     => $mk?->rumpun,
                'semester'            => $mk?->semester,
                'sks_teori'           => (int) ($mk?->sks_teori ?? 0),
                'sks_praktik'         => (int) ($mk?->sks_praktik ?? 0),
                'bentuk_pembelajaran' => $this->bentukPembelajaran($mk),
                'status_mk'           => $mk?->sifat ? ucfirst($mk->sifat) : null,
                'prasyarat'           => $base['prasyarat']
                    ? trim($base['prasyarat']['kode'] . ($base['prasyarat']['nama'] ? ' — ' . $base['prasyarat']['nama'] : ''))
                    : null,
                'tahun_kurikulum'     => $kurikulum?->tahun,
                'tahun_akademik'      => $rps->tahun_akademik,
                'tim_dosen'           => collect($pengampu)->pluck('nama')->filter()->values()->all(),
                'kode_dokumen'        => $rps->kode_dokumen,
                'tanggal_penyusunan'  => $tanggalSusun,
                'revisi'              => max(0, (int) $rps->versi - 1),
            ],
            'otorisasi'        => $this->otorisasi($rps, $pengampu, $kel, $denganBerkas),
            'deskripsi'        => $mk?->deskripsi_singkat,
            'cpl'              => $this->cplDibebankan($cpls, $cpmkCpl, $instIds, $rps->kode_mk, $kel),
            'cpmk'             => $cpmks->map(fn($c) => [
                'kode'      => $c->kode,
                'deskripsi' => $c->deskripsi,
                'cpl'       => array_values(array_unique($cplPerCpmk[$c->id] ?? [])),
            ])->all(),
            'matriks_cpl_cpmk' => [
                'cpl'   => $cpls->pluck('kode')->all(),
                'baris' => $cpmks->map(fn($c) => [
                    'cpmk' => $c->kode,
                    'cek'  => $cpls->mapWithKeys(fn($l) => [$l->kode => in_array($l->kode, $cplPerCpmk[$c->id] ?? [], true)])->all(),
                ])->all(),
            ],
            'bahan_kajian'     => $this->bahanKajian($minggu, $instIds, $rps->kode_mk, $cpmkPerMinggu, $cpmkPerCpl),
            'sub_cpmk'         => $subs->map(fn($s) => [
                'kode'      => $s->kode,
                'deskripsi' => $s->deskripsi,
                'cpmk'      => $s->cpmk?->kode,
            ])->all(),
            'cpmk_sub'         => $cpmks->map(fn($c) => [
                'cpmk' => $c->kode,
                'sub'  => $subs->where('cpmk_id', $c->id)->pluck('kode')->values()->all(),
            ])->all(),
            'mingguan'         => $this->mingguan($minggu, $komponen, $subs, $cpmkUjian),
            'asesmen'          => array_map(fn($g) => array_intersect_key($g, array_flip(['komponen', 'teknik', 'instrumen', 'cpmk', 'bobot'])), $kelompok),
            'total_bobot'      => round((float) $komponen->sum(fn($k) => (float) $k->bobot_persen), 2),
            'matriks_asesmen'  => [
                'cpmk'  => $cpmks->pluck('kode')->all(),
                'baris' => array_map(fn($g) => [
                    'komponen' => $g['komponen'],
                    'cek'      => $cpmks->mapWithKeys(fn($c) => [$c->kode => in_array($c->kode, $g['cpmk'], true)])->all(),
                ], $kelompok),
            ],
            'kriteria'         => array_map(fn($g) => [
                'asesmen'   => $g['komponen'],
                'aspek'     => $g['aspek'],
                'skala'     => $g['skala'],
                'instrumen' => $g['instrumen_rubrik'],
            ], $kelompok),
            'media'            => $this->media($minggu, $base, $kel),
            'skala_nilai'      => $this->skalaNilai($rps->institusi_id),
            'evaluasi'         => $this->evaluasi($kelompok, $cpmks->pluck('kode')->all()),
            'referensi'        => $this->referensi($instIds, $rps->kode_mk),
            'lampiran'         => $this->lampiran($komponen, $mk, $kel),
        ];
    }

    private function bentukPembelajaran(?MataKuliah $mk): ?string
    {
        if (! $mk) {
            return null;
        }
        $teori = (int) $mk->sks_teori;
        $praktik = (int) $mk->sks_praktik;
        if ($teori > 0 && $praktik > 0) {
            return 'Kombinasi (Kuliah & Praktikum)';
        }
        if ($praktik > 0 || $mk->jenis_mk === 'praktikum') {
            return 'Praktikum';
        }

        return 'Kuliah';
    }

    /** @return list<array{jabatan:string,nama:?string,nidn:?string,tanggal:?string,ttd:?array}> */
    private function otorisasi(RpsVersion $rps, array $pengampu, array $kel, bool $denganBerkas): array
    {
        $o = is_array($kel['otorisasi'] ?? null) ? $kel['otorisasi'] : [];
        $final = $rps->pernahDisetujui();
        $akunNidn = fn(?string $nidn) => $nidn ? User::where('nidn', $nidn)->first() : null;

        $koor = collect($pengampu)->firstWhere('peran', 'koordinator');
        $koorAkun = $koor ? $akunNidn($koor['nidn'] ?? null) : null;
        if (! $koor && $rps->koordinator_mk) {
            $koorAkun = User::find($rps->koordinator_mk);
            $koor = $koorAkun ? ['nama' => $koorAkun->name, 'nidn' => $koorAkun->nidn] : null;
        }
        $kaprodiAkun = $rps->approved_by ? User::find($rps->approved_by) : null;
        $kaprodi = $kaprodiAkun ? ['nama' => $kaprodiAkun->name, 'nidn' => $kaprodiAkun->nidn] : null;

        // [kunci, jabatan, turunan, akun turunan, waktu tanda tangan, tanggal bila belum bertanda tangan]
        $baris = [
            ['koordinator_mk', 'Koordinator Mata Kuliah', $koor, $koorAkun, $rps->submitted_at ?? ($final ? $rps->approved_at : null), $rps->tanggal_penyusunan],
            ['koordinator_bk', 'Koordinator Bidang Keahlian', null, null, $final ? $rps->approved_at : null, null],
            ['ketua_prodi', 'Ketua Program Studi', $kaprodi, $kaprodiAkun, $final ? $rps->approved_at : null, $rps->approved_at],
        ];

        return array_map(function ($b) use ($o, $rps, $akunNidn, $denganBerkas) {
            [$key, $jabatan, $turunan, $akunTurunan, $waktu, $tanggal] = $b;
            $manual = array_filter(array_map(fn($v) => trim((string) $v), is_array($o[$key] ?? null) ? $o[$key] : []));
            // Isian manual menentukan penanda tangan hanya lewat NIDN, agar TTD akun lain tak menempel pada nama manual.
            $akun = $manual !== [] ? $akunNidn($manual['nidn'] ?? null) : $akunTurunan;

            $ttd = null;
            if ($waktu && $akun && $this->gambar->ada($akun->ttd_path)) {
                $ttd = [
                    'waktu' => $waktu->format('d-m-Y H:i'),
                    'kode'  => strtoupper(substr(hash_hmac('sha256', implode('|', [$rps->ulid, $rps->versi, $akun->id, $key]), (string) config('app.key')), 0, 12)),
                ];
                if ($denganBerkas) {
                    $ttd['file'] = $this->gambar->absolut($akun->ttd_path);
                }
            }

            return [
                'jabatan' => $jabatan,
                'nama'    => $manual['nama'] ?? ($manual === [] ? ($turunan['nama'] ?? null) : null),
                'nidn'    => $manual['nidn'] ?? ($manual === [] ? ($turunan['nidn'] ?? null) : null),
                'tanggal' => optional($ttd ? $waktu : $tanggal)->format('d-m-Y'),
                'ttd'     => $ttd,
            ];
        }, $baris);
    }

    /** Berkas logo terdekat: prodi → fakultas → universitas. */
    public function logoFile(int $institusiId): ?string
    {
        foreach (Institusi::idsHierarkiKeAtas($institusiId) as $id) {
            $path = $this->gambar->absolut(Institusi::whereKey($id)->value('logo_path'));
            if ($path) {
                return $path;
            }
        }

        return null;
    }

    /**
     * CPL yang dibebankan + kontribusi. Isian manual `peran_cpl` menang; bila
     * kosong, CPL berbobot tertinggi di peta MK×CPL (atau paling banyak didukung
     * CPMK) = Utama, sisanya Pendukung.
     */
    private function cplDibebankan(Collection $cpls, Collection $cpmkCpl, array $instIds, string $kodeMk, array $kel): array
    {
        if ($cpls->isEmpty()) {
            return [];
        }
        $manual = is_array($kel['peran_cpl'] ?? null) ? $kel['peran_cpl'] : [];
        $bobotMk = MkCpl::whereIn('institusi_id', $instIds)->where('kode_mk', $kodeMk)
            ->whereIn('cpl_id', $cpls->pluck('id'))->pluck('bobot', 'cpl_id')
            ->map(fn($b) => $b !== null ? (float) $b : null)->filter(fn($b) => $b !== null);
        $skor = $bobotMk->isNotEmpty()
            ? $cpls->mapWithKeys(fn($c) => [$c->id => (float) ($bobotMk[$c->id] ?? 0)])
            : $cpls->mapWithKeys(fn($c) => [$c->id => (float) $cpmkCpl->where('cpl_id', $c->id)->count()]);
        $maks = $skor->max();

        return $cpls->map(function ($c) use ($manual, $skor, $maks) {
            $peran = strtolower((string) ($manual[$c->kode] ?? ''));
            if (! in_array($peran, ['utama', 'pendukung'], true)) {
                $peran = ($skor[$c->id] ?? 0) >= $maks ? 'utama' : 'pendukung';
            }

            return [
                'kode'       => $c->kode,
                'deskripsi'  => $c->deskripsi,
                'kontribusi' => ucfirst($peran),
            ];
        })->values()->all();
    }

    /**
     * Bahan kajian tertaut MK beserta CPMK pekan yang materinya menyebut BK tsb;
     * bila tak disebut, CPMK yang mendukung CPL tempat BK dipetakan (peta CPL×BK).
     * Tanpa BK tertaut: pokok materi pekan (teks sebelum "—"/"[") jadi daftar.
     */
    private function bahanKajian(Collection $minggu, array $instIds, string $kodeMk, callable $cpmkPerMinggu, array $cpmkPerCpl): array
    {
        $belajar = $minggu->filter(fn($m) => $this->jenisUjian($m) === null);
        $tertaut = MkBahanKajian::whereIn('institusi_id', $instIds)->where('kode_mk', $kodeMk)
            ->with('bahanKajian')->get()
            ->filter(fn($x) => trim((string) ($x->bahanKajian?->nama ?? '')) !== '')
            ->unique('bahan_kajian_id')->values();
        $cplPerBk = CplBahanKajian::whereIn('bahan_kajian_id', $tertaut->pluck('bahan_kajian_id'))->get()
            ->groupBy('bahan_kajian_id')->map(fn($g) => $g->pluck('cpl_id')->all());

        $daftar = $tertaut->map(fn($x) => ['nama' => trim($x->bahanKajian->nama), 'bk_id' => $x->bahan_kajian_id]);
        if ($daftar->isEmpty()) {
            $daftar = $belajar->map(fn($m) => trim((string) preg_split('/\s+[—–-]\s+|\s*\[/u', (string) $m->materi_pustaka)[0]))
                ->filter()->unique()->values()->map(fn($n) => ['nama' => $n, 'bk_id' => null]);
        }

        return $daftar->map(function ($d) use ($belajar, $cpmkPerMinggu, $cplPerBk, $cpmkPerCpl) {
            $cpmk = $belajar->filter(fn($m) => mb_stripos((string) $m->materi_pustaka, $d['nama']) !== false)
                ->flatMap(fn($m) => $cpmkPerMinggu($m))->unique()->values();
            if ($cpmk->isEmpty() && $d['bk_id']) {
                $cpmk = collect($cplPerBk[$d['bk_id']] ?? [])->flatMap(fn($id) => $cpmkPerCpl[$id] ?? [])->unique()->values();
            }

            return ['nama' => $d['nama'], 'cpmk' => $cpmk->sort(fn($a, $b) => strnatcasecmp($a, $b))->values()->all()];
        })->all();
    }

    private function jenisUjian($m): ?string
    {
        $t = strtolower((string) $m->materi_pustaka);
        if (preg_match('/\buts\b|ujian tengah|evaluasi tengah/', $t)) {
            return 'UTS';
        }
        if (preg_match('/\buas\b|ujian akhir|evaluasi akhir/', $t)) {
            return 'UAS';
        }

        return null;
    }

    /** Pisah teks "Kriteria: …\nTeknik: …" menjadi [kriteria, teknik]. */
    private function pisahKriteria(?string $teks): array
    {
        $t = $this->print->formatKriteria($teks);
        if ($t === '') {
            return [null, null];
        }
        $bagian = preg_split('/\n(?=(?:Teknik|Bentuk)\s*:)/u', $t, 2);
        $kriteria = trim((string) preg_replace('/^Kriteria\s*:\s*/u', '', $bagian[0]));
        $teknik = isset($bagian[1]) ? trim((string) preg_replace('/^(?:Teknik|Bentuk)\s*:\s*/u', '', $bagian[1])) : null;

        return [$kriteria !== '' ? $kriteria : null, $teknik !== '' ? $teknik : null];
    }

    /**
     * CPMK cakupan ujian: UTS = pekan belajar sebelum pekan UTS, UAS = sisanya.
     *
     * @return array{UTS:list<string>,UAS:list<string>}
     */
    private function cpmkPerUjian(Collection $minggu, callable $cpmkPerMinggu): array
    {
        $out = ['UTS' => [], 'UAS' => []];
        $pekanUts = $minggu->first(fn($m) => $this->jenisUjian($m) === 'UTS')?->minggu_ke;
        foreach ($minggu as $m) {
            if ($this->jenisUjian($m) !== null) {
                continue;
            }
            $tujuan = $pekanUts !== null && $m->minggu_ke < $pekanUts ? 'UTS' : 'UAS';
            $out[$tujuan] = array_merge($out[$tujuan], $cpmkPerMinggu($m));
        }
        if ($pekanUts === null) {
            $out['UTS'] = $out['UAS'];
        }
        foreach ($out as $k => $v) {
            $v = array_values(array_unique($v));
            usort($v, 'strnatcasecmp');
            $out[$k] = $v;
        }

        return $out;
    }

    private function mingguan(Collection $minggu, Collection $komponen, Collection $subs, array $cpmkUjian): array
    {
        $perMinggu = $komponen->groupBy(fn($k) => (int) ($k->minggu_ke ?? 0));

        return $minggu->map(function ($m) use ($perMinggu, $subs, $cpmkUjian) {
            $ujian = $this->jenisUjian($m);
            $tugas = $perMinggu->get((int) $m->minggu_ke, collect());
            [$kriteria, $teknik] = $this->pisahKriteria($m->teknik_kriteria_penilaian);

            $kodeSub = collect($this->print->subIdsMinggu($m))
                ->map(fn($id) => $subs->firstWhere('id', $id)?->kode)->filter()->values()->all();
            if ($ujian && $kodeSub === []) {
                $kodeSub = $tugas->map(fn($k) => $k->subCpmk?->cpmk?->kode ?? $k->subCpmk?->kode)
                    ->filter()->unique()->values()->all() ?: $cpmkUjian[$ujian];
            }

            $bentuk = array_values(array_filter([
                trim((string) $m->bentuk_luring) !== '' ? 'Luring: ' . trim($m->bentuk_luring) : null,
                trim((string) $m->bentuk_daring) !== '' ? 'Daring: ' . trim($m->bentuk_daring) : null,
                trim((string) $m->metode_pembelajaran) !== '' ? 'Metode: ' . trim($m->metode_pembelajaran) : null,
            ]));

            $komponenUjian = $ujian
                ? $tugas->first(fn($k) => strtolower((string) $k->jenis) === strtolower($ujian))
                : null;
            $instrUjian = trim((string) ($komponenUjian?->instrumen ?? '')) ?: $teknik;
            $asesmen = array_values(array_filter(array_merge(
                [$ujian ? $ujian . ($instrUjian ? ' – ' . $instrUjian : '') : $teknik],
                $tugas->reject(fn($k) => $komponenUjian && $k->is($komponenUjian))
                    ->map(fn($k) => trim($k->nama . ($k->instrumen ? ' — ' . $k->instrumen : '')))->all(),
            )));

            $bukti = trim((string) $m->bukti_produk);

            return [
                'minggu_ke'          => (int) $m->minggu_ke,
                'ujian'              => $ujian,
                'sub_cpmk'           => $kodeSub,
                'indikator'          => $m->indikator,
                'kriteria'           => $kriteria,
                'bahan_kajian'       => $m->materi_pustaka,
                'bentuk_metode'      => $bentuk,
                'pengalaman_belajar' => $m->pengalaman_belajar,
                'media_sumber'       => $m->media_sumber,
                'asesmen'            => $asesmen,
                'bukti_produk'       => $bukti !== '' ? $bukti : ($tugas->pluck('nama')->filter()->implode('; ') ?: null),
                'bobot'              => $m->bobot_penilaian !== null ? (float) $m->bobot_penilaian : null,
                'waktu'              => $this->print->formatEstimasi($m->estimasi_waktu),
                'rincian_pertemuan'  => is_array($m->rincian_pertemuan) && $m->rincian_pertemuan !== [] ? $m->rincian_pertemuan : null,
            ];
        })->all();
    }

    /**
     * Rekap komponen penilaian per jenis (bagian I/J/K/N): satu baris per
     * kategori, bobot dijumlah, CPMK digabung.
     */
    private function kelompokAsesmen(Collection $komponen, array $cpmkUjian): array
    {
        $grup = [];
        foreach ($komponen as $k) {
            $jenis = strtolower(trim((string) $k->jenis)) ?: 'lainnya';
            $key = isset(self::JENIS_ASESMEN[$jenis]) ? self::JENIS_ASESMEN[$jenis][0] : ucfirst(str_replace('_', ' ', $jenis));
            $grup[$key] ??= [
                'komponen'  => $key,
                'teknik'    => self::JENIS_ASESMEN[$jenis][1] ?? ucfirst(str_replace('_', ' ', $jenis)),
                'item'      => [],
                'instrumen' => [],
                'cpmk'      => [],
                'bobot'     => 0.0,
                'aspek'     => [],
                'level'     => null,
                'rubrik'    => [],
                'ujian'     => in_array($jenis, ['uts', 'uas', 'kuis'], true),
            ];
            $g = &$grup[$key];
            $g['item'][] = $k->nama;
            if (trim((string) $k->instrumen) !== '') {
                $g['instrumen'][] = trim($k->instrumen);
            }
            $cpmk = $k->subCpmk?->cpmk?->kode;
            if ($cpmk) {
                $g['cpmk'][] = $cpmk;
            } elseif (in_array($jenis, ['uts', 'uas'], true)) {
                $g['cpmk'] = array_merge($g['cpmk'], $cpmkUjian[strtoupper($jenis)]);
            }
            $g['bobot'] += (float) $k->bobot_persen;
            if ($k->rubrik && $k->rubrik->kriteria->isNotEmpty()) {
                foreach ($k->rubrik->kriteria->sortBy('urutan') as $kr) {
                    $g['aspek'][] = trim((string) $kr->kriteria);
                }
                $g['level'] = max((int) $g['level'], (int) ($k->rubrik->jumlah_level_skala ?: 4));
                $g['rubrik'][] = 'Rubrik ' . str_replace('_', ' ', (string) $k->rubrik->jenis);
            }
            unset($g);
        }

        return array_values(array_map(function ($g) {
            $cpmk = array_values(array_unique($g['cpmk']));
            usort($cpmk, 'strnatcasecmp');
            $instrumen = array_values(array_unique($g['instrumen']));
            $aspek = array_values(array_unique(array_filter($g['aspek'])));

            return [
                'komponen'         => $g['komponen'],
                'teknik'           => $g['teknik'],
                'instrumen'        => $instrumen !== [] ? implode('; ', $instrumen) : null,
                'cpmk'             => $cpmk,
                'bobot'            => round($g['bobot'], 2),
                'aspek'            => $aspek !== [] ? implode(', ', $aspek) : ($instrumen !== [] ? implode('; ', $instrumen) : null),
                'skala'            => $g['level'] ? '1–' . $g['level'] : '0–100',
                'instrumen_rubrik' => $g['rubrik'] !== []
                    ? implode('; ', array_values(array_unique($g['rubrik'])))
                    : ($g['ujian'] ? 'Kisi-kisi + soal' : ($instrumen !== [] ? implode('; ', $instrumen) : null)),
            ];
        }, $grup));
    }

    private function media(Collection $minggu, array $base, array $kel): array
    {
        $teks = fn($k) => ($v = trim((string) ($kel[$k] ?? ''))) !== '' ? $v : null;
        $belajar = $minggu->filter(fn($m) => $this->jenisUjian($m) === null);

        $metode = $belajar->flatMap(fn($m) => preg_split('/\s*[,;\/]\s*(?:dan\s+)?|\s+dan\s+/u', trim((string) $m->metode_pembelajaran)) ?: [])
            ->map(fn($s) => trim($s, " .\t\n"))->filter()
            ->countBy(fn($s) => mb_strtolower($s))->sortDesc()->keys()->take(6)
            ->map(fn($s) => mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1))->implode(', ');
        $media = $belajar->pluck('media_sumber')->map(fn($s) => trim((string) $s))->filter()->unique()->take(6)->implode('; ');

        $luring = isset($kel['proporsi_luring']) && is_numeric($kel['proporsi_luring'])
            ? max(0, min(100, (int) $kel['proporsi_luring'])) : null;

        return [
            'media_pembelajaran'       => $teks('media_pembelajaran') ?? ($media !== '' ? $media : null),
            'lms'                      => $teks('lms'),
            'pengalaman_belajar_utama' => $teks('pengalaman_belajar_utama') ?? ($metode !== '' ? $metode : null),
            'proporsi_luring'          => $luring,
            'proporsi_daring'          => $luring !== null ? 100 - $luring : null,
            'sumber_belajar_utama'     => $teks('sumber_belajar_utama')
                ?? (($p = array_slice($base['pustaka_utama'] ?? [], 0, 3)) !== [] ? implode('; ', array_map(fn($s) => rtrim((string) $s, '. '), $p)) : null),
        ];
    }

    /** @return list<array{rentang:string,huruf:string,keterangan:?string}> */
    public function skalaNilai(?int $institusiId): array
    {
        $rentang = $this->estimasi->nilaiAturan($institusiId, 'skala_nilai')['rentang'] ?? null;
        $rows = is_array($rentang) && $rentang !== [] ? $rentang : self::SKALA_NILAI_DEFAULT;
        usort($rows, fn($a, $b) => (float) ($b['min'] ?? 0) <=> (float) ($a['min'] ?? 0));
        $fmt = fn($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');

        return array_map(function ($r) use ($fmt) {
            $min = (float) ($r['min'] ?? 0);
            $max = (float) ($r['max'] ?? 0);

            return [
                'rentang'    => $min <= 0 ? '< ' . $fmt(ceil($max)) : $fmt($min) . '–' . $fmt(floor($max)),
                'huruf'      => (string) ($r['huruf'] ?? ''),
                'keterangan' => isset($r['keterangan']) && $r['keterangan'] !== '' ? (string) $r['keterangan'] : null,
            ];
        }, $rows);
    }

    private function evaluasi(array $kelompok, array $semuaCpmk): array
    {
        $rows = array_map(fn($g) => [
            'komponen'  => $g['komponen'],
            'bukti'     => trim(implode(' + ', array_filter([$g['instrumen_rubrik'] ? rtrim($g['instrumen_rubrik'], '. ') : null, 'hasil mahasiswa']))),
            'cpmk'      => $g['cpmk'],
            'digunakan' => 'Ketercapaian CPMK',
        ], $kelompok);
        if ($rows !== []) {
            $rows[] = [
                'komponen'  => 'Rekap seluruh asesmen',
                'bukti'     => 'Data nilai',
                'cpmk'      => $semuaCpmk,
                'digunakan' => 'Kontribusi terhadap CPL',
            ];
        }

        return $rows;
    }

    /** @return list<array{jenis:string,sitasi:string}> */
    private function referensi(array $instIds, string $kodeMk): array
    {
        $refs = Referensi::whereIn('institusi_id', $instIds)->where('kode_mk', $kodeMk)->orderBy('id')->get();
        $out = [];
        foreach (self::JENIS_REFERENSI as $tipe => $label) {
            $i = 1;
            foreach ($refs->filter(fn($r) => ($r->tipe ?: 'utama') === $tipe) as $r) {
                $out[] = ['jenis' => in_array($tipe, ['utama', 'pendukung'], true) ? $label . ' ' . $i++ : $label, 'sitasi' => $r->sitasi];
            }
        }

        return $out;
    }

    /** Checklist lampiran: isian manual menang; selain itu ditandai dari komponen & rubrik yang ada. */
    private function lampiran(Collection $komponen, ?MataKuliah $mk, array $kel): array
    {
        $manual = is_array($kel['lampiran'] ?? null) ? $kel['lampiran'] : [];
        $jenis = $komponen->toBase()->map(fn($k) => strtolower((string) $k->jenis));
        $adaRubrik = fn(callable $f) => $komponen->contains(fn($k) => $f($k) && $k->rubrik && $k->rubrik->kriteria->isNotEmpty());
        $praktik = (int) ($mk?->sks_praktik ?? 0) > 0 || $mk?->jenis_mk === 'praktikum';

        $turunan = [
            'kisi_kisi'           => $jenis->intersect(['uts', 'uas', 'kuis'])->isNotEmpty(),
            'rubrik_tugas'        => $adaRubrik(fn($k) => in_array(strtolower((string) $k->jenis), ['tugas', 'studi_kasus'], true)),
            'rubrik_proyek'       => $adaRubrik(fn($k) => strtolower((string) $k->jenis) === 'proyek' || preg_match('/proyek|pbl|project/i', (string) $k->nama)),
            'checklist_praktikum' => $praktik && ($jenis->intersect(['osce', 'skill_assessment', 'laporan_praktikum', 'responsi'])->isNotEmpty()
                || $komponen->contains(fn($k) => in_array($k->rubrik?->jenis, ['checklist_skill', 'lembar_observasi'], true))),
            'lkm'                 => false,
            'logbook'             => false,
            'refleksi'            => false,
            'lainnya'             => false,
        ];

        $out = [];
        foreach (self::LAMPIRAN as $key => [$label, $ketentuan]) {
            $out[] = [
                'key'       => $key,
                'label'     => $label,
                'ketentuan' => $ketentuan,
                'ada'       => array_key_exists($key, $manual) ? (bool) $manual[$key] : $turunan[$key],
                'manual'    => array_key_exists($key, $manual),
            ];
        }

        return $out;
    }
}
