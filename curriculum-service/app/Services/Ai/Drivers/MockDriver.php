<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Contracts\Driver;
use App\Services\Ai\LlmResult;

/**
 * Driver tiruan untuk dev/test tanpa API key. Bila prompt memuat contoh skema
 * JSON (blok terakhir), driver mengembalikannya apa adanya agar alur generator
 * bertahap (yang menuntut keluaran JSON) dapat diuji end-to-end. Selain itu
 * mengembalikan echo teks. Token diestimasi kasar (~1 token / 4 karakter).
 */
class MockDriver implements Driver
{
    public function run(array $model, string $system, string $prompt, array $params): LlmResult
    {
        $text = $this->echoJsonOr($prompt, "[MOCK:{$model['model']}] " . trim($prompt));

        return new LlmResult(
            text: $text,
            inputTokens: $this->estimate($system . $prompt),
            outputTokens: $this->estimate($text),
            latencyMs: 1,
            modelVersion: $model['model'] . '-mock',
            raw: ['mock' => true],
        );
    }

    /**
     * Ambil baris JSON valid TERAKHIR dari prompt (generator menaruh contoh
     * skema pada baris terakhir), agar mock menghasilkan keluaran berbentuk
     * skema. Jika tidak ada, kembalikan teks fallback.
     */
    private function echoJsonOr(string $prompt, string $fallback): string
    {
        $lines = preg_split('/\r?\n/', trim($prompt)) ?: [];
        foreach (array_reverse($lines) as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '' && is_array(json_decode($trimmed, true))) {
                return $trimmed;
            }
        }

        return $fallback;
    }

    private function estimate(string $s): int
    {
        return (int) max(1, ceil(mb_strlen($s) / 4));
    }
}
