<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ButirAcuan
 *
 * Menyertakan status pemenuhan bila relasi `pemenuhan` sudah di-scope ke satu
 * institusi (lihat KerangkaAcuanController@show).
 */
class ButirAcuanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pemenuhan = $this->relationLoaded('pemenuhan') ? $this->pemenuhan->first() : null;

        return [
            'id'                => $this->id,
            'kerangka_acuan_id' => $this->kerangka_acuan_id,
            'parent_id'         => $this->parent_id,
            'kategori'          => $this->kategori,
            'kode'              => $this->kode,
            'deskripsi'         => $this->deskripsi,
            'wajib'             => $this->wajib,
            'urutan'            => $this->urutan,
            'status'            => $pemenuhan?->status ?? 'belum',
            'catatan'           => $pemenuhan?->catatan,
            'rekomendasi_ai'    => (bool) ($pemenuhan?->rekomendasi_ai ?? false),
        ];
    }
}
