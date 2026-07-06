<?php

namespace App\Services\Ai;

use App\Models\AiInteraksi;

/**
 * Hasil satu tugas AI: keluaran model + biaya (USD) + baris log AI_INTERAKSI.
 */
class AiOutcome
{
    public function __construct(
        public LlmResult $result,
        public float $biaya,
        public ?AiInteraksi $interaksi = null,
    ) {}

    public function text(): string
    {
        return $this->result->text;
    }

    public function failed(): bool
    {
        return $this->result->failed();
    }
}
