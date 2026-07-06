<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\BadanRujukan */
class BadanRujukanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'institusi_id'   => $this->institusi_id,
            'nama'           => $this->nama,
            'jenis'          => $this->jenis,
            'disiplin'       => $this->disiplin,
            'dokumen_count'  => $this->whenCounted('dokumen'),
            'created_at'     => $this->created_at,
        ];
    }
}
