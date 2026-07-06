<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KonfigurasiAturanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'institusi_id'          => $this->institusi_id,
            'jenis_aturan'          => $this->jenis_aturan,
            'nilai'                 => $this->nilai,
            'badan_rujukan_id'      => $this->badan_rujukan_id,
            'referensi_dokumen_id'  => $this->referensi_dokumen_id,
            'referensi_halaman'     => $this->referensi_halaman,
            'created_at'            => $this->created_at,
            'updated_at'            => $this->updated_at,
        ];
    }
}
