<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PromptTemplate */
class PromptTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'jenis_output' => $this->jenis_output,
            'jenis_mk'      => $this->jenis_mk,
            'institusi_id' => $this->institusi_id,
            'sistem_prompt' => $this->sistem_prompt,
            'skema_output' => $this->skema_output
                ? json_encode($this->skema_output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null,
            'versi'         => $this->versi,
            'aktif'         => (bool) $this->aktif,
            'created_at'    => $this->created_at?->toIso8601String(),
            'updated_at'    => $this->updated_at?->toIso8601String(),
        ];
    }
}
