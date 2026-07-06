<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Cpl */
class CplResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'institusi_id' => $this->institusi_id,
            'kurikulum_id' => $this->kurikulum_id,
            'kode'         => $this->kode,
            'deskripsi'    => $this->deskripsi,
            'aspek'        => $this->aspek,
            'level_kkni'   => $this->level_kkni,
            'sumber'       => $this->sumber,
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ];
    }
}
