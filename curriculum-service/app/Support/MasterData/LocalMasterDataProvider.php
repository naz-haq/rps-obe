<?php

namespace App\Support\MasterData;

use Illuminate\Support\Facades\DB;

/**
 * Implementasi Tahap 1 (standalone): master data disimpan di tabel lokal
 * `mata_kuliah` dan `dosen` (diisi via upload/Column-Mapping).
 *
 * Query pakai kunci natural + institusi_id (isolasi multi-tenant).
 * Saat migrasi ke ekosistem, kelas ini diganti AcademicCoreMasterDataProvider.
 */
class LocalMasterDataProvider implements MasterDataProvider
{
    public function findCourseByKode(string $institusiId, string $kodeMk): ?CourseRef
    {
        $row = DB::table('mata_kuliah')
            ->where('institusi_id', $institusiId)
            ->where('kode_mk', $kodeMk)
            ->first();

        return $row ? $this->toCourseRef($row) : null;
    }

    /** @return array<int,CourseRef> */
    public function listCourses(string $institusiId): array
    {
        return DB::table('mata_kuliah')
            ->where('institusi_id', $institusiId)
            ->orderBy('kode_mk')
            ->get()
            ->map(fn($row) => $this->toCourseRef($row))
            ->all();
    }

    public function findLecturerByNidn(string $institusiId, string $nidn): ?LecturerRef
    {
        $row = DB::table('dosen')
            ->where('institusi_id', $institusiId)
            ->where('nidn', $nidn)
            ->first();

        return $row ? $this->toLecturerRef($row) : null;
    }

    /** @return array<int,LecturerRef> */
    public function listLecturers(string $institusiId): array
    {
        return DB::table('dosen')
            ->where('institusi_id', $institusiId)
            ->orderBy('nama')
            ->get()
            ->map(fn($row) => $this->toLecturerRef($row))
            ->all();
    }

    private function toCourseRef(object $row): CourseRef
    {
        return new CourseRef(
            kodeMk: $row->kode_mk,
            nama: $row->nama,
            sks: (int) $row->sks_teori + (int) $row->sks_praktik,
            semester: $row->semester !== null ? (int) $row->semester : null,
            prodiKode: $row->prodi_kode ?? null,
            externalId: null,
        );
    }

    private function toLecturerRef(object $row): LecturerRef
    {
        return new LecturerRef(
            nidn: $row->nidn,
            nama: $row->nama,
            externalId: null,
        );
    }
}
