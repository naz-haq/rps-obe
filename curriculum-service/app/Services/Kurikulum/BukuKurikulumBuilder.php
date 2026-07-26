<?php

namespace App\Services\Kurikulum;

use App\Models\BahanKajian;
use App\Models\Cpl;
use App\Models\CplBahanKajian;
use App\Models\Institusi;
use App\Models\Kurikulum;
use App\Models\MataKuliah;
use App\Models\MkCpl;
use App\Models\ProfilLulusan;
use App\Models\RpsVersion;

/**
 * Perakit Buku Kurikulum (dokumen kurikulum prodi).
 *
 * Seluruh isi bersifat DETERMINISTIK — dirakit langsung dari entitas & matriks
 * kurikulum (Profil Lulusan, CPL, Bahan Kajian, Mata Kuliah beserta pemetaannya
 * dan ringkasan RPS). Narasi AI (bila diminta) hanya melengkapi bagian prosa dan
 * tetap berpijak pada data ini; angka/tabel tidak pernah berasal dari AI.
 */
class BukuKurikulumBuilder
{
    /**
     * Status kelengkapan prasyarat: setiap Mata Kuliah kurikulum WAJIB sudah
     * memiliki RPS ter-commit (rps_version) sebelum Buku Kurikulum dirakit.
     *
     * @return array{total_mk:int,mk_ada_rps:int,mk_belum_rps:list<array{kode_mk:string,nama:string,semester:int|null}>,lengkap:bool}
     */
    public function kelengkapan(Kurikulum $kurikulum): array
    {
        $mks = MataKuliah::where('kurikulum_id', $kurikulum->id)
            ->orderByRaw('semester IS NULL, semester')
            ->orderBy('kode_mk')
            ->get(['kode_mk', 'nama', 'semester', 'institusi_id']);

        $adaRps = RpsVersion::query()
            ->where('institusi_id', $kurikulum->institusi_id)
            ->whereIn('kode_mk', $mks->pluck('kode_mk'))
            ->pluck('kode_mk')
            ->unique()
            ->flip();

        $belum = $mks
            ->filter(fn($mk) => ! $adaRps->has($mk->kode_mk))
            ->map(fn($mk) => [
                'kode_mk'  => $mk->kode_mk,
                'nama'     => $mk->nama,
                'semester' => $mk->semester,
            ])
            ->values();

        return [
            'total_mk'     => $mks->count(),
            'mk_ada_rps'   => $mks->count() - $belum->count(),
            'mk_belum_rps' => $belum->all(),
            'lengkap'      => $mks->count() > 0 && $belum->isEmpty(),
        ];
    }

    /**
     * Rakit struktur Buku Kurikulum yang lengkap (deterministik).
     *
     * @return array<string,mixed>
     */
    public function build(Kurikulum $kurikulum): array
    {
        return [
            'identitas'       => $this->identitas($kurikulum),
            'profil_lulusan'  => $this->profilLulusan($kurikulum),
            'cpl'             => $this->cpl($kurikulum),
            'matriks_pl_cpl'  => $this->matriksPlCpl($kurikulum),
            'bahan_kajian'    => $this->bahanKajian($kurikulum),
            'matriks_cpl_bk'  => $this->matriksCplBk($kurikulum),
            'mata_kuliah'     => $this->mataKuliahPerSemester($kurikulum),
            'matriks_mk_cpl'  => $this->matriksMkCpl($kurikulum),
            'rps_ringkas'     => $this->rpsRingkas($kurikulum),
        ];
    }

    /** Identitas program studi (hierarki institusi + metadata kurikulum + VMTS). */
    private function identitas(Kurikulum $kurikulum): array
    {
        $prodi = null;
        $fakultas = null;
        $universitas = null;

        $inst = Institusi::find($kurikulum->institusi_id);
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

        // VMTS: versi yang dipilih kurikulum (kunci ke edisi tertentu).
        $vmts = $kurikulum->vmts()->first();

        return [
            'kurikulum' => [
                'nama'            => $kurikulum->nama,
                'kode'            => $kurikulum->kode,
                'tahun'           => $kurikulum->tahun,
                'status'          => $kurikulum->status,
                'tanggal_berlaku' => optional($kurikulum->tanggal_berlaku)->format('Y-m-d'),
            ],
            'prodi' => $prodi ? [
                'nama'       => $prodi->nama,
                'jenjang'    => $prodi->jenjang,
                'gelar'      => $prodi->gelar,
                'akreditasi' => $prodi->akreditasi,
            ] : null,
            'fakultas'    => $fakultas ? ['nama' => $fakultas->nama] : null,
            'universitas' => $universitas ? [
                'nama'            => $universitas->nama,
                'nilai_institusi' => $universitas->nilai_institusi,
            ] : null,
            'vmts' => $vmts ? [
                'label'    => $vmts->label,
                'visi'     => $vmts->visi,
                'misi'     => $vmts->misi ?? [],
                'tujuan'   => $vmts->tujuan ?? [],
                'strategi' => $vmts->strategi ?? [],
            ] : null,
        ];
    }

    /** @return list<array{kode:string,deskripsi:string}> */
    private function profilLulusan(Kurikulum $kurikulum): array
    {
        return ProfilLulusan::where('kurikulum_id', $kurikulum->id)
            ->orderBy('kode')
            ->get(['kode', 'deskripsi'])
            ->map(fn($p) => ['kode' => (string) $p->kode, 'deskripsi' => (string) $p->deskripsi])
            ->all();
    }

    /** @return list<array{kode:string,deskripsi:string,aspek:?string,level_kkni:mixed}> */
    private function cpl(Kurikulum $kurikulum): array
    {
        return Cpl::where('kurikulum_id', $kurikulum->id)
            ->orderBy('kode')
            ->get(['kode', 'deskripsi', 'aspek', 'level_kkni'])
            ->map(fn($c) => [
                'kode'       => (string) $c->kode,
                'deskripsi'  => (string) $c->deskripsi,
                'aspek'      => $c->aspek,
                'level_kkni' => $c->level_kkni,
            ])
            ->all();
    }

    /**
     * Matriks Profil Lulusan × CPL: untuk tiap PL, kode CPL yang menopangnya.
     *
     * @return list<array{profil:string,cpl:list<string>}>
     */
    private function matriksPlCpl(Kurikulum $kurikulum): array
    {
        return ProfilLulusan::where('kurikulum_id', $kurikulum->id)
            ->with('cpl:id,kode')
            ->orderBy('kode')
            ->get(['id', 'kode'])
            ->map(fn($pl) => [
                'profil' => (string) $pl->kode,
                'cpl'    => $pl->cpl->pluck('kode')->map(fn($k) => (string) $k)->values()->all(),
            ])
            ->all();
    }

    /** @return list<array{nama:string,deskripsi:?string}> */
    private function bahanKajian(Kurikulum $kurikulum): array
    {
        return BahanKajian::where('kurikulum_id', $kurikulum->id)
            ->orderBy('nama')
            ->get(['nama', 'deskripsi'])
            ->map(fn($b) => ['nama' => (string) $b->nama, 'deskripsi' => $b->deskripsi])
            ->all();
    }

    /**
     * Matriks CPL × Bahan Kajian: untuk tiap CPL, nama bahan kajian penopang.
     *
     * @return list<array{cpl:string,bahan_kajian:list<string>}>
     */
    private function matriksCplBk(Kurikulum $kurikulum): array
    {
        $cpls = Cpl::where('kurikulum_id', $kurikulum->id)->orderBy('kode')->get(['id', 'kode']);

        $peta = []; // cpl_id => [nama BK]
        CplBahanKajian::query()
            ->whereIn('cpl_id', $cpls->pluck('id'))
            ->with('bahanKajian:id,nama')
            ->get()
            ->each(function ($row) use (&$peta) {
                $nama = $row->bahanKajian?->nama;
                if ($nama) {
                    $peta[$row->cpl_id][] = (string) $nama;
                }
            });

        return $cpls->map(fn($c) => [
            'cpl'          => (string) $c->kode,
            'bahan_kajian' => $peta[$c->id] ?? [],
        ])->all();
    }

    /**
     * Struktur Mata Kuliah dikelompokkan per semester.
     *
     * @return list<array{semester:int|null,mata_kuliah:list<array{kode_mk:string,nama:string,sks:int,sks_teori:int,sks_praktik:int,sifat:?string,jenis_mk:?string}>}>
     */
    private function mataKuliahPerSemester(Kurikulum $kurikulum): array
    {
        return MataKuliah::where('kurikulum_id', $kurikulum->id)
            ->orderByRaw('semester IS NULL, semester')
            ->orderBy('kode_mk')
            ->get()
            ->groupBy('semester')
            ->map(fn($grup, $semester) => [
                'semester'    => $semester === '' ? null : (int) $semester,
                'mata_kuliah' => $grup->map(fn($mk) => [
                    'kode_mk'     => (string) $mk->kode_mk,
                    'nama'        => (string) $mk->nama,
                    'sks'         => (int) $mk->sks,
                    'sks_teori'   => (int) $mk->sks_teori,
                    'sks_praktik' => (int) $mk->sks_praktik,
                    'sifat'       => $mk->sifat,
                    'jenis_mk'    => $mk->jenis_mk,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Matriks CPL × Mata Kuliah: untuk tiap MK, kode CPL yang dibebankan.
     *
     * @return list<array{kode_mk:string,nama:string,cpl:list<string>}>
     */
    private function matriksMkCpl(Kurikulum $kurikulum): array
    {
        $mks = MataKuliah::where('kurikulum_id', $kurikulum->id)
            ->orderByRaw('semester IS NULL, semester')
            ->orderBy('kode_mk')
            ->get(['kode_mk', 'nama', 'institusi_id']);

        $cplKodeById = Cpl::where('kurikulum_id', $kurikulum->id)->pluck('kode', 'id');

        $peta = []; // kode_mk => [kode CPL]
        MkCpl::query()
            ->where('institusi_id', $kurikulum->institusi_id)
            ->whereIn('kode_mk', $mks->pluck('kode_mk'))
            ->get(['kode_mk', 'cpl_id'])
            ->each(function ($row) use (&$peta, $cplKodeById) {
                $kode = $cplKodeById[$row->cpl_id] ?? null;
                if ($kode) {
                    $peta[$row->kode_mk][] = (string) $kode;
                }
            });

        return $mks->map(fn($mk) => [
            'kode_mk' => (string) $mk->kode_mk,
            'nama'    => (string) $mk->nama,
            'cpl'     => $peta[$mk->kode_mk] ?? [],
        ])->all();
    }

    /**
     * Ringkasan RPS per Mata Kuliah (versi terbaru): jumlah CPMK, Sub-CPMK,
     * pekan, dan komponen penilaian.
     *
     * @return list<array{kode_mk:string,nama:string,versi:int,jumlah_cpmk:int,jumlah_sub_cpmk:int,jumlah_minggu:int,jumlah_komponen:int}>
     */
    private function rpsRingkas(Kurikulum $kurikulum): array
    {
        $mks = MataKuliah::where('kurikulum_id', $kurikulum->id)
            ->orderByRaw('semester IS NULL, semester')
            ->orderBy('kode_mk')
            ->get(['kode_mk', 'nama', 'institusi_id']);

        return $mks->map(function ($mk) use ($kurikulum) {
            $rps = RpsVersion::query()
                ->where('institusi_id', $kurikulum->institusi_id)
                ->where('kode_mk', $mk->kode_mk)
                ->orderByDesc('versi')
                ->with(['minggu.subCpmk.cpmk'])
                ->withCount(['minggu', 'komponenPenilaian'])
                ->first();

            if (! $rps) {
                return [
                    'kode_mk'         => (string) $mk->kode_mk,
                    'nama'            => (string) $mk->nama,
                    'versi'           => 0,
                    'jumlah_cpmk'     => 0,
                    'jumlah_sub_cpmk' => 0,
                    'jumlah_minggu'   => 0,
                    'jumlah_komponen' => 0,
                ];
            }

            $subCpmk = $rps->minggu->pluck('subCpmk')->filter();

            return [
                'kode_mk'         => (string) $mk->kode_mk,
                'nama'            => (string) $mk->nama,
                'versi'           => (int) $rps->versi,
                'jumlah_cpmk'     => $subCpmk->pluck('cpmk')->filter()->unique('id')->count(),
                'jumlah_sub_cpmk' => $subCpmk->unique('id')->count(),
                'jumlah_minggu'   => (int) $rps->minggu_count,
                'jumlah_komponen' => (int) $rps->komponen_penilaian_count,
            ];
        })->all();
    }
}
