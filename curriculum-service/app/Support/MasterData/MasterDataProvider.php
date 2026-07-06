<?php

namespace App\Support\MasterData;

/**
 * Kontrak sumber master data (MK, dosen) — SATU seam untuk migrasi mulus.
 *
 * Tahap 1 (standalone):  LocalMasterDataProvider   → baca tabel lokal.
 * Tahap 2 (ekosistem):   AcademicCoreMasterDataProvider → baca API Academic Core.
 *
 * Yang berganti hanya implementasi ini; model & logika OBE tidak berubah.
 * Semua entitas OBE merujuk MK/dosen lewat KUNCI NATURAL (kode_mk / nidn).
 */
interface MasterDataProvider
{
    public function findCourseByKode(string $institusiId, string $kodeMk): ?CourseRef;

    /** @return array<int,CourseRef> */
    public function listCourses(string $institusiId): array;

    public function findLecturerByNidn(string $institusiId, string $nidn): ?LecturerRef;

    /** @return array<int,LecturerRef> */
    public function listLecturers(string $institusiId): array;
}
