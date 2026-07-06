<?php

namespace App\Services\Ai;

/**
 * Hasil pemanggilan model yang sudah dinormalisasi lintas provider.
 * Diport dari benchmark-harness (App\Services\LLM\LlmResult).
 */
class LlmResult
{
    public function __construct(
        public string $text = '',
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $cacheReadTokens = 0,
        public int $cacheWriteTokens = 0,
        public int $latencyMs = 0,
        public ?string $modelVersion = null,
        public ?string $error = null,
        public array $raw = [],
    ) {}

    public function failed(): bool
    {
        return $this->error !== null;
    }

    public function toArray(): array
    {
        return [
            'text'               => $this->text,
            'input_tokens'       => $this->inputTokens,
            'output_tokens'      => $this->outputTokens,
            'cache_read_tokens'  => $this->cacheReadTokens,
            'cache_write_tokens' => $this->cacheWriteTokens,
            'latency_ms'         => $this->latencyMs,
            'model_version'      => $this->modelVersion,
            'error'              => $this->error,
        ];
    }
}
