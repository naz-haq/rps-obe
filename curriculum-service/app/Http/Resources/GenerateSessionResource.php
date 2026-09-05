<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\GenerateSession */
class GenerateSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $rps = $this->rpsVersion;
        return [
            'id'               => $this->id,
            'institusi_id'     => $this->institusi_id,
            'mk_id'            => $this->mk_id,
            'kode_mk'          => $this->whenLoaded('mataKuliah', fn() => $this->mataKuliah?->kode_mk),
            'nama_mk'          => $this->whenLoaded('mataKuliah', fn() => $this->mataKuliah?->nama),
            'sumber'           => $this->sumber,
            'tahap'            => $this->tahap,
            'status'           => $this->status,
            'revisi'           => $this->revisi ?? 0,
            'status_bagian'    => $this->status_bagian ?? [],
            'draf'             => $this->draf ?? [],
            'catatan_validasi' => $this->catatan_validasi ?? [],
            'konteks_tambahan' => $this->konteks_tambahan,
            'rps_version_id'   => $this->rps_version_id,
            'rps_status'       => $rps?->status,
            'can_reopen'       => $this->status === 'committed' && $rps
                && in_array($rps->status, ['draft', 'review', 'revisi'], true)
                && ! $rps->pernahDisetujui(),
            'user_id'          => $this->user_id,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
