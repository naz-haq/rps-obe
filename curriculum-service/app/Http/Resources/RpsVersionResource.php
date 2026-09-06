<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RpsVersion */
class RpsVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $session = \App\Models\GenerateSession::where('rps_version_id', $this->id)->latest('id')->first();
        // Nama MK: prioritas via mk_id sesi (FK andal, lintas institusi) seperti
        // generator; fallback ke kode_mk (RPS bisa berbeda institusi dari MK
        // pada hierarki tenant, jadi JANGAN kunci pada institusi_id).
        $namaMk = ($session && $session->mk_id
            ? \App\Models\MataKuliah::whereKey($session->mk_id)->value('nama')
            : null)
            ?? \App\Models\MataKuliah::where('kode_mk', $this->kode_mk)->value('nama');
        return [
            'id'                 => $this->id,
            'ulid'               => $this->ulid,
            'institusi_id'       => $this->institusi_id,
            'kode_mk'            => $this->kode_mk,
            'nama_mk'            => $namaMk,
            'versi'              => $this->versi,
            'status'             => $this->status,
            'generate_session_id' => $session?->id,
            'editing_in_generator' => $session && $session->status !== 'committed',
            'can_reopen' => $session && $session->status === 'committed'
                && in_array($this->status, ['draft', 'review', 'revisi'], true)
                && ! $this->pernahDisetujui(),
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
