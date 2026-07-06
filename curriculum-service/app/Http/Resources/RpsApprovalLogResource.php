<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RpsApprovalLog */
class RpsApprovalLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'rps_version_id' => $this->rps_version_id,
            'aksi'           => $this->aksi,
            'dari_status'    => $this->dari_status,
            'ke_status'      => $this->ke_status,
            'catatan'        => $this->catatan,
            'actor_id'       => $this->actor_id,
            'actor_nama'     => $this->actor_nama,
            'created_at'     => $this->created_at,
        ];
    }
}
