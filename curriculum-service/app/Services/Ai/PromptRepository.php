<?php

namespace App\Services\Ai;

use App\Models\PromptTemplate;

/**
 * Pusat resolusi prompt: gabungkan default (config/prompts.php) dengan override
 * DB (prompt_template) yang dapat dikelola lewat UI. Dipakai bersama oleh
 * RpsGeneratorService (generator bertahap) & GroundingValidator (validasi).
 *
 * Prioritas efektif: override DB (tenant > jenis_mk spesifik > versi terbaru)
 * lalu fallback default config. Bila override kosong/nonaktif → kembali default.
 */
class PromptRepository
{
    /**
     * Prompt efektif untuk sebuah slot.
     *
     * @return array{system:string,schema:string,sumber:string,template_id:?int}
     */
    public function resolve(string $slot, ?int $institusiId = null, ?string $jenisMk = null): array
    {
        $default = config("prompts.slots.{$slot}", []);
        $template = $this->override($slot, $institusiId, $jenisMk);

        $system = $template?->sistem_prompt ?: ($default['system'] ?? '');
        $schema = $template && ! empty($template->skema_output)
            ? (string) json_encode($template->skema_output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : ($default['schema'] ?? '');

        return [
            'system'      => $system,
            'schema'      => $schema,
            'sumber'      => $template ? 'override' : 'default',
            'template_id' => $template?->id,
        ];
    }

    /** Baris override aktif paling relevan untuk slot (atau null bila tak ada). */
    public function override(string $slot, ?int $institusiId, ?string $jenisMk): ?PromptTemplate
    {
        return PromptTemplate::query()
            ->where('jenis_output', $slot)
            ->where('aktif', true)
            ->where(fn($q) => $q->where('institusi_id', $institusiId)->orWhereNull('institusi_id'))
            ->where(fn($q) => $q->where('jenis_mk', $jenisMk)->orWhereNull('jenis_mk'))
            ->orderByRaw('institusi_id IS NULL')  // template tenant diprioritaskan
            ->orderByRaw('jenis_mk IS NULL')      // yang spesifik jenis_mk diprioritaskan
            ->orderByDesc('versi')
            ->first();
    }

    /** Daftar semua slot prompt bawaan (untuk katalog UI). */
    public function slots(): array
    {
        return config('prompts.slots', []);
    }
}
