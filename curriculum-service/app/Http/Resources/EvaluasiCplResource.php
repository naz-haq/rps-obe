<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvaluasiCplResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'cpl_id'           => $this->cpl_id,
            'cpl'             => $this->whenLoaded('cpl', fn() => [
                'id'        => $this->cpl->id,
                'kode'      => $this->cpl->kode,
                'deskripsi' => $this->cpl->deskripsi,
            ]),
            'periode'          => $this->periode,
            'ringkasan_naratif' => $this->ringkasan_naratif,
            'status'           => $this->status,
            'tindak_lanjut'    => TindakLanjutResource::collection($this->whenLoaded('tindakLanjut')),
            'tindak_lanjut_count' => $this->whenCounted('tindakLanjut'),
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
