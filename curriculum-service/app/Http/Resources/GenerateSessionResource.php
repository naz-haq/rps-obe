<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\GenerateSession */
class GenerateSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'institusi_id'     => $this->institusi_id,
            'mk_id'            => $this->mk_id,
            'kode_mk'          => $this->whenLoaded('mataKuliah', fn() => $this->mataKuliah?->kode_mk),
            'nama_mk'          => $this->whenLoaded('mataKuliah', fn() => $this->mataKuliah?->nama),
            'sumber'           => $this->sumber,
            'tahap'            => $this->tahap,
            'status'           => $this->status,
            'status_bagian'    => $this->status_bagian ?? [],
            'draf'             => $this->draf ?? [],
            'catatan_validasi' => $this->catatan_validasi ?? [],
            'rps_version_id'   => $this->rps_version_id,
            'user_id'          => $this->user_id,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
