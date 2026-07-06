<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Spatie\Permission\Models\Role
 */
class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $meta = config('rbac.roles.' . $this->name, []);

        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'label'       => $meta['label'] ?? $this->name,
            'deskripsi'   => $meta['deskripsi'] ?? null,
            'bawaan'      => array_key_exists($this->name, config('rbac.roles', [])),
            'permissions' => $this->permissions->pluck('name'),
            'users_count' => $this->users_count ?? $this->users()->count(),
            'created_at'  => $this->created_at,
        ];
    }
}
