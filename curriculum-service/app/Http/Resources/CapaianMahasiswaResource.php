<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CapaianMahasiswaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                          => $this->id,
            'kode_mk'                     => $this->kode_mk,
            'sub_cpmk_id'                 => $this->sub_cpmk_id,
            'sub_cpmk'                    => $this->whenLoaded('subCpmk', fn() => $this->subCpmk?->kode),
            'cpmk_id'                     => $this->cpmk_id,
            'cpmk'                        => $this->whenLoaded('cpmk', fn() => $this->cpmk?->kode),
            'angkatan'                    => $this->angkatan,
            'jumlah_mahasiswa'            => $this->jumlah_mahasiswa,
            'nilai_rata_rata'             => $this->nilai_rata_rata !== null ? (float) $this->nilai_rata_rata : null,
            'persentase_capaian_minimal'  => $this->persentase_capaian_minimal !== null ? (float) $this->persentase_capaian_minimal : null,
            'created_at'                  => $this->created_at,
        ];
    }
}
