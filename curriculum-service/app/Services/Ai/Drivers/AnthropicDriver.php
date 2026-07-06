<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Contracts\Driver;
use App\Services\Ai\LlmResult;
use Illuminate\Support\Facades\Http;

/**
 * Driver Anthropic (Claude) — Messages API.
 * Diport dari benchmark-harness.
 */
class AnthropicDriver implements Driver
{
    public function run(array $model, string $system, string $prompt, array $params): LlmResult
    {
        $start = microtime(true);
        try {
            $response = Http::withHeaders([
                'x-api-key'         => $model['api_key'],
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(180)->post(rtrim($model['base_url'], '/') . '/messages', [
                'model'       => $model['model'],
                'max_tokens'  => $params['max_tokens'],
                'temperature' => $params['temperature'],
                'system'      => $system,
                'messages'    => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            $latency = (int) round((microtime(true) - $start) * 1000);
            $data = $response->json();

            if (isset($data['error'])) {
                return new LlmResult(error: $data['error']['message'] ?? 'Anthropic error', latencyMs: $latency);
            }

            $text = collect($data['content'] ?? [])
                ->map(fn($b) => $b['text'] ?? '')
                ->implode("\n");

            $usage = $data['usage'] ?? [];

            return new LlmResult(
                text: trim($text),
                inputTokens: (int) ($usage['input_tokens'] ?? 0),
                outputTokens: (int) ($usage['output_tokens'] ?? 0),
                cacheReadTokens: (int) ($usage['cache_read_input_tokens'] ?? 0),
                cacheWriteTokens: (int) ($usage['cache_creation_input_tokens'] ?? 0),
                latencyMs: $latency,
                modelVersion: $data['model'] ?? $model['model'],
                raw: $usage,
            );
        } catch (\Throwable $e) {
            return new LlmResult(
                error: $e->getMessage(),
                latencyMs: (int) round((microtime(true) - $start) * 1000),
            );
        }
    }
}
