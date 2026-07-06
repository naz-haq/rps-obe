<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ValidasiOverlapResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'institusi_id'    => $this->institusi_id,
            'keterampilan_id' => $this->keterampilan_id,
            'keterampilan'    => $this->whenLoaded('keterampilan', fn() => [
                'id'          => $this->keterampilan->id,
                'deskripsi'   => $this->keterampilan->deskripsi,
                'domain'      => $this->keterampilan->domain,
                'bahan_kajian' => $this->keterampilan->relationLoaded('bahanKajian') && $this->keterampilan->bahanKajian
                    ? $this->keterampilan->bahanKajian->nama
                    : null,
            ]),
            'mk_terlibat'     => $this->mk_terlibat ?? [],
            'jumlah_mk'       => is_array($this->mk_terlibat) ? count($this->mk_terlibat) : 0,
            'status'          => $this->status,
            'analisis'        => $this->analisis,
            'rekomendasi'     => $this->rekomendasi,
            'reviewed_by'     => $this->reviewed_by,
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
