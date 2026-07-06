<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Notifikasi
 */
class NotifikasiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'institusi_id' => $this->institusi_id,
            'user_id'      => $this->user_id,
            'jenis'        => $this->jenis,
            'konten'       => $this->konten,
            'status'       => $this->status,
            'created_at'   => $this->created_at,
        ];
    }
}
