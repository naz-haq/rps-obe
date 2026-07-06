<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TargetCplResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'cpl_id'            => $this->cpl_id,
            'cpl'              => $this->whenLoaded('cpl', fn() => [
                'id'        => $this->cpl->id,
                'kode'      => $this->cpl->kode,
                'deskripsi' => $this->cpl->deskripsi,
            ]),
            'angkatan'          => $this->angkatan,
            'ambang_nilai'      => $this->ambang_nilai !== null ? (float) $this->ambang_nilai : null,
            'persentase_target' => $this->persentase_target !== null ? (float) $this->persentase_target : null,
            'created_at'        => $this->created_at,
        ];
    }
}
