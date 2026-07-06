<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DokumenRujukan */
class DokumenRujukanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'institusi_id'     => $this->institusi_id,
            'badan_rujukan_id' => $this->badan_rujukan_id,
            'badan_rujukan'    => $this->whenLoaded('badanRujukan', fn() => $this->badanRujukan?->nama),
            'jenis'            => $this->jenis,
            'judul'            => $this->judul,
            'file_asal'        => $this->file_asal,
            'status_indexing'  => $this->status_indexing,
            'chunk_count'      => $this->whenCounted('chunks'),
            'created_at'       => $this->created_at,
        ];
    }
}
