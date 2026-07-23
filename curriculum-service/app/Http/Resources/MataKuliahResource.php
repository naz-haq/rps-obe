<?php

namespace App\Http\Resources;

use App\Services\Rps\EstimasiWaktuService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\MataKuliah */
class MataKuliahResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'ulid'              => $this->ulid,
            'institusi_id'      => $this->institusi_id,
            'institusi_nama'    => $this->institusi?->nama,
            'kurikulum_id'      => $this->kurikulum_id,
            'kode_mk'           => $this->kode_mk,
            'nama'              => $this->nama,
            'jenis_mk'          => $this->jenis_mk,
            'pola'              => $this->pola ?? 'reguler',
            'jumlah_minggu'     => $this->jumlah_minggu,
            'sifat'             => $this->sifat,
            'rumpun'            => $this->rumpun,
            'deskripsi_singkat' => $this->deskripsi_singkat,
            'sks_teori'         => $this->sks_teori,
            'sks_praktik'       => $this->sks_praktik,
            'sks'               => $this->sks,
            'semester'          => $this->semester,
            'prodi_kode'        => $this->prodi_kode,
            'prasyarat_kode'    => $this->prasyarat_kode,
            'estimasi_waktu'    => app(EstimasiWaktuService::class)->untukMataKuliah($this->resource),
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
