<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RpsVersion */
class RpsVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'ulid'               => $this->ulid,
            'institusi_id'       => $this->institusi_id,
            'kode_mk'            => $this->kode_mk,
            'versi'              => $this->versi,
            'status'             => $this->status,
            'bahasa'             => $this->bahasa,
            'kode_dokumen'       => $this->kode_dokumen,
            'created_by'         => $this->created_by,
            'koordinator_mk'     => $this->koordinator_mk,
            'approved_by'        => $this->approved_by,
            'submitted_at'       => $this->submitted_at,
            'approved_at'        => $this->approved_at,
            'catatan_review'     => $this->catatan_review,
            'tanggal_penyusunan' => optional($this->tanggal_penyusunan)->toDateString(),
            'minggu_count'       => $this->whenCounted('minggu'),
            'komponen_count'     => $this->whenCounted('komponenPenilaian'),
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
        ];
    }
}
