<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\KerangkaAcuan */
class KerangkaAcuanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'badan_rujukan_id' => $this->badan_rujukan_id,
            'badan_rujukan'    => $this->whenLoaded('badanRujukan', fn() => $this->badanRujukan?->nama),
            'dokumen_id'       => $this->dokumen_id,
            'nama'             => $this->nama,
            'versi'            => $this->versi,
            'tanggal_berlaku'  => optional($this->tanggal_berlaku)->toDateString(),
            'butir_count'      => $this->whenCounted('butir'),
            'created_at'       => $this->created_at,
        ];
    }
}
