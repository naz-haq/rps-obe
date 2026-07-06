<?php

namespace App\Support\MasterData;

/**
 * DTO referensi Mata Kuliah — netral terhadap sumbernya.
 * Tahap 1 diisi dari tabel lokal; Tahap 2 dari API Academic Core.
 * Selalu dirujuk lewat KUNCI NATURAL (kode_mk), bukan ID internal.
 */
final readonly class CourseRef
{
    public function __construct(
        public string $kodeMk,
        public string $nama,
        public int $sks,
        public ?int $semester = null,
        public ?string $prodiKode = null,
        public ?string $externalId = null,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'kode_mk' => $this->kodeMk,
            'nama' => $this->nama,
            'sks' => $this->sks,
            'semester' => $this->semester,
            'prodi_kode' => $this->prodiKode,
            'external_id' => $this->externalId,
        ];
    }
}
