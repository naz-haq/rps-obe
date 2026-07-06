<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'email'         => $this->email,
            'nidn'          => $this->nidn,
            'jabatan'       => $this->jabatan,
            'is_active'     => (bool) $this->is_active,
            'institusi_id'  => $this->institusi_id,
            'institusi'     => $this->whenLoaded('institusi', fn() => $this->institusi ? [
                'id'    => $this->institusi->id,
                'nama'  => $this->institusi->nama,
                'jenis' => $this->institusi->jenis,
            ] : null),
            'roles'         => $this->getRoleNames(),
            'permissions'   => $this->getAllPermissions()->pluck('name'),
            'created_at'    => $this->created_at,
        ];
    }
}
