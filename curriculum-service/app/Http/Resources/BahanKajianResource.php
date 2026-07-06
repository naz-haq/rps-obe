<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\BahanKajian */
class BahanKajianResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'institusi_id' => $this->institusi_id,
            'kurikulum_id' => $this->kurikulum_id,
            'nama'         => $this->nama,
            'deskripsi'    => $this->deskripsi,
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ];
    }
}
