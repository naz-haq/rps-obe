<?php

namespace App\Support\MasterData;

/**
 * DTO referensi Dosen — netral terhadap sumbernya.
 * Selalu dirujuk lewat KUNCI NATURAL (nidn), bukan ID internal.
 */
final readonly class LecturerRef
{
    public function __construct(
        public string $nidn,
        public string $nama,
        public ?string $externalId = null,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'nidn' => $this->nidn,
            'nama' => $this->nama,
            'external_id' => $this->externalId,
        ];
    }
}
