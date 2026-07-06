<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Kurikulum */
class KurikulumResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'ulid'            => $this->ulid,
            'institusi_id'    => $this->institusi_id,
            'kode'            => $this->kode,
            'nama'            => $this->nama,
            'tahun'           => $this->tahun,
            'status'          => $this->status,
            'tanggal_berlaku' => optional($this->tanggal_berlaku)->toDateString(),
            'tanggal_pensiun' => optional($this->tanggal_pensiun)->toDateString(),
            'mengganti_id'    => $this->mengganti_id,
            'mata_kuliah_count' => $this->whenCounted('mataKuliah'),
            'cpl_count'         => $this->whenCounted('cpl'),
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
