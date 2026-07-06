<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\AuditLog
 */
class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'institusi_id' => $this->institusi_id,
            'user_id'      => $this->user_id,
            'actor_nama'   => $this->meta['actor_nama'] ?? null,
            'action'       => $this->action,
            'entity'       => $this->entity,
            'entity_id'    => $this->entity_id,
            'meta'         => $this->meta,
            'created_at'   => $this->created_at,
        ];
    }
}
