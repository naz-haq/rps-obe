<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Taksonomi */
class TaksonomiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'institusi_id' => $this->institusi_id,
            'domain'       => $this->domain,
            'kerangka'     => $this->kerangka,
            'kode'         => $this->kode,
            'nama'         => $this->nama,
            'level'        => $this->level,
            'deskripsi'    => $this->deskripsi,
            'kata_kerja'   => $this->kata_kerja ?? [],
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ];
    }
}
