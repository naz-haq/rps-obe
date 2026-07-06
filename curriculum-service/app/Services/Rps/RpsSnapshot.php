<?php

namespace App\Services\Rps;

use App\Models\Cpl;
use App\Models\Cpmk;
use App\Models\GenerateSession;
use App\Models\MataKuliah;
use App\Models\RpsVersion;

/**
 * Membangun "snapshot" RPS ternormalisasi (MK + CPL + CPMK + Sub-CPMK + rencana
 * mingguan + komponen penilaian) dari dua sumber:
 *  - GenerateSession (draf yang sedang disusun di builder, sebelum commit), atau
 *  - RpsVersion (RPS resmi yang sudah committed).
 *
 * Dipakai bersama oleh audit keselarasan (fitur #6) & chat konsultan (fitur #7).
 */
class RpsSnapshot
{
    /** Snapshot dari draf sesi generate (belum committed). */
    public function fromSession(GenerateSession $session): array
    {
        $mk = $session->mataKuliah;
        $draf = $session->draf ?? [];

        return [
            'mata_kuliah' => $this->mkInfo($mk),
            'cpl'         => $this->cplList($mk),
            'cpmk'        => array_map(fn($c) => [
                'kode'       => $c['kode'] ?? '',
                'deskripsi'  => $c['deskripsi'] ?? '',
                'taksonomi'  => $this->taksonomiCodes($c['taksonomi_kode'] ?? null),
                'cpl_kode'   => $c['cpl_kode'] ?? [],
            ], $draf['cpmk']['cpmk'] ?? []),
            'sub_cpmk'    => array_map(fn($s) => [
                'kode'       => $s['kode'] ?? '',
                'cpmk_kode'  => $s['cpmk_kode'] ?? null,
                'deskripsi'  => $s['deskripsi'] ?? '',
                'taksonomi'  => $this->taksonomiCodes($s['taksonomi_kode'] ?? null),
                'indikator'  => $s['indikator'] ?? [],
            ], $draf['sub_cpmk']['sub_cpmk'] ?? []),
            'minggu'      => array_map(fn($m) => [
                'minggu_ke'           => $m['minggu_ke'] ?? null,
                'sub_cpmk_kode'       => $m['sub_cpmk_kode'] ?? null,
                'indikator'           => $m['indikator'] ?? null,
                'kriteria_penilaian'  => $m['kriteria_penilaian'] ?? null,
                'metode_pembelajaran' => $m['metode_pembelajaran'] ?? null,
                'bentuk_luring'       => $m['bentuk_luring'] ?? null,
                'bentuk_daring'       => $m['bentuk_daring'] ?? null,
                'pengalaman_belajar'  => $m['pengalaman_belajar'] ?? null,
                'materi_pustaka'      => $m['materi_pustaka'] ?? ($m['bahan_kajian'] ?? null),
                'bobot_penilaian'     => $m['bobot_penilaian'] ?? null,
            ], $draf['mingguan']['minggu'] ?? []),
            'komponen'    => array_map(fn($k) => [
                'nama'          => $k['nama'] ?? '',
                'jenis'         => $k['jenis'] ?? null,
                'bobot_persen'  => $k['bobot_persen'] ?? null,
                'sub_cpmk_kode' => $k['sub_cpmk_kode'] ?? null,
                'minggu_ke'     => $k['minggu_ke'] ?? null,
            ], $draf['penilaian']['komponen'] ?? []),
        ];
    }

    /** Snapshot dari RPS resmi (committed). */
    public function fromRpsVersion(RpsVersion $rps): array
    {
        $mk = MataKuliah::where('institusi_id', $rps->institusi_id)
            ->where('kode_mk', $rps->kode_mk)
            ->first();

        $cpmks = Cpmk::where('institusi_id', $rps->institusi_id)
            ->where('kode_mk', $rps->kode_mk)
            ->with(['cpl:id,kode', 'taksonomi:id,kode', 'subCpmk.taksonomi:id,kode', 'subCpmk.indikator:id,sub_cpmk_id,deskripsi'])
            ->get();

        $subList = [];
        foreach ($cpmks as $cpmk) {
            foreach ($cpmk->subCpmk as $sub) {
                $subList[] = [
                    'kode'      => $sub->kode,
                    'cpmk_kode' => $cpmk->kode,
                    'deskripsi' => $sub->deskripsi,
                    'taksonomi' => $this->taksonomiCodes($sub->taksonomi_kode ?: $sub->taksonomi?->kode),
                    'indikator' => $sub->indikator->pluck('deskripsi')->all(),
                ];
            }
        }

        $rps->loadMissing(['minggu.subCpmk:id,kode', 'komponenPenilaian.subCpmk:id,kode']);

        return [
            'mata_kuliah' => $this->mkInfo($mk),
            'cpl'         => $this->cplList($mk),
            'cpmk'        => $cpmks->map(fn(Cpmk $c) => [
                'kode'      => $c->kode,
                'deskripsi' => $c->deskripsi,
                'taksonomi' => $this->taksonomiCodes($c->taksonomi_kode ?: $c->taksonomi?->kode),
                'cpl_kode'  => $c->cpl->pluck('kode')->all(),
            ])->all(),
            'sub_cpmk'    => $subList,
            'minggu'      => $rps->minggu->map(fn($m) => [
                'minggu_ke'           => $m->minggu_ke,
                'sub_cpmk_kode'       => $m->subCpmk?->kode,
                'indikator'           => $m->indikator,
                'kriteria_penilaian'  => $m->teknik_kriteria_penilaian,
                'metode_pembelajaran' => $m->metode_pembelajaran,
                'bentuk_luring'       => $m->bentuk_luring,
                'bentuk_daring'       => $m->bentuk_daring,
                'pengalaman_belajar'  => $m->pengalaman_belajar,
                'materi_pustaka'      => $m->materi_pustaka,
                'bobot_penilaian'     => $m->bobot_penilaian,
            ])->all(),
            'komponen'    => $rps->komponenPenilaian->map(fn($k) => [
                'nama'          => $k->nama,
                'jenis'         => $k->jenis,
                'bobot_persen'  => $k->bobot_persen,
                'sub_cpmk_kode' => $k->subCpmk?->kode,
                'minggu_ke'     => $k->minggu_ke,
            ])->all(),
        ];
    }

    /**
     * Normalisasi taksonomi menjadi daftar kode (terima array, string tunggal, atau null).
     *
     * @return list<string>
     */
    private function taksonomiCodes(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        $items = is_array($raw) ? $raw : [$raw];
        $out = [];
        foreach ($items as $k) {
            $k = trim((string) $k);
            if ($k !== '' && ! in_array($k, $out, true)) {
                $out[] = $k;
            }
        }
        return $out;
    }

    private function mkInfo(?MataKuliah $mk): array
    {
        if (! $mk) {
            return ['kode_mk' => null, 'nama' => null, 'sks' => null, 'semester' => null, 'jenis_mk' => null, 'deskripsi' => null];
        }

        return [
            'kode_mk'   => $mk->kode_mk,
            'nama'      => $mk->nama,
            'sks'       => $mk->sks,
            'semester'  => $mk->semester,
            'jenis_mk'  => $mk->jenis_mk,
            'deskripsi' => $mk->deskripsi_singkat,
        ];
    }

    /** @return array<int,array{kode:string,deskripsi:string,aspek:?string}> */
    private function cplList(?MataKuliah $mk): array
    {
        if (! $mk || ! $mk->kurikulum_id) {
            return [];
        }

        return Cpl::where('kurikulum_id', $mk->kurikulum_id)
            ->orderBy('kode')
            ->get(['kode', 'deskripsi', 'aspek'])
            ->map(fn(Cpl $c) => [
                'kode'      => $c->kode,
                'deskripsi' => $c->deskripsi,
                'aspek'     => $c->aspek,
            ])->all();
    }
}
