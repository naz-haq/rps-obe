<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TindakLanjutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'evaluasi_cpl_id'  => $this->evaluasi_cpl_id,
            'sub_cpmk_id'      => $this->sub_cpmk_id,
            'sub_cpmk'         => $this->whenLoaded('subCpmk', fn() => $this->subCpmk?->kode),
            'catatan'          => $this->catatan,
            'prioritas'        => $this->prioritas,
            'status'           => $this->status,
            'created_at'       => $this->created_at,
        ];
    }
}
