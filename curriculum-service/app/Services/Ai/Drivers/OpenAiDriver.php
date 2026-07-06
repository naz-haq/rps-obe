<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Contracts\Driver;
use App\Services\Ai\LlmResult;
use Illuminate\Support\Facades\Http;

/**
 * Driver untuk semua API yang kompatibel format OpenAI Chat Completions
 * (OpenAI/GPT, dan yang lain via base_url override).
 * Diport dari benchmark-harness.
 */
class OpenAiDriver implements Driver
{
    /** Status HTTP transien yang layak dicoba ulang (overload/rate-limit/gateway). */
    private const TRANSIENT_STATUSES = [408, 429, 500, 502, 503, 504, 529];

    /** Jumlah percobaan maksimum untuk galat transien (1 asli + N ulang). */
    private const MAX_ATTEMPTS = 3;

    public function run(array $model, string $system, string $prompt, array $params): LlmResult
    {
        $start = microtime(true);

        $payload = [
            'model'       => $model['model'],
            'temperature' => $params['temperature'],
            'max_tokens'  => $params['max_tokens'],
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        // Gemini 2.5 mengaktifkan "thinking" secara default; token reasoning
        // ikut menghabiskan max_tokens sehingga pada keluaran besar (mis. tahap
        // 'mingguan') konten balik KOSONG. Matikan lewat lapisan kompatibel-
        // OpenAI Gemini (reasoning_effort: none) agar seluruh anggaran token
        // dipakai untuk keluaran nyata. Hanya untuk provider gemini agar tidak
        // mengganggu OpenAI/DeepSeek.
        if (($model['provider'] ?? null) === 'gemini') {
            $payload['reasoning_effort'] = 'none';
        }

        $url = rtrim($model['base_url'], '/') . '/chat/completions';
        $lastError = 'permintaan gagal';

        // Coba-ulang untuk galat transien (mis. Gemini "503 model overloaded"
        // yang kerap muncul pada tahap besar 'mingguan'). Backoff eksponensial
        // ringan; galat non-transien langsung dikembalikan tanpa mengulang.
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $response = Http::withToken($model['api_key'])
                    ->timeout(180)
                    ->post($url, $payload);

                $latency = (int) round((microtime(true) - $start) * 1000);
                $data = $response->json();

                // Deteksi galat dalam DUA bentuk: objek {"error":{...}} (OpenAI) ATAU
                // array [{"error":{...}}] (Gemini via lapisan kompatibel-OpenAI).
                // Sertakan status HTTP non-2xx agar galat nyata (mis. 429 kuota habis)
                // TIDAK tersamar menjadi "konten kosong".
                $err = $data['error'] ?? (is_array($data) && isset($data[0]['error']) ? $data[0]['error'] : null);
                if ($err !== null || $response->failed()) {
                    $msg = is_array($err) ? ($err['message'] ?? 'API error') : ((string) ($err ?? 'permintaan gagal'));
                    $lastError = 'HTTP ' . $response->status() . ': ' . $msg;

                    if ($this->isTransient($response->status()) && $attempt < self::MAX_ATTEMPTS) {
                        $this->backoff($attempt);
                        continue;
                    }

                    return new LlmResult(error: $lastError, latencyMs: $latency);
                }

                return $this->parseSuccess($data, $model, $latency);
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                // Galat koneksi/timeout juga transien → ulangi bila masih ada jatah.
                if ($attempt < self::MAX_ATTEMPTS) {
                    $this->backoff($attempt);
                    continue;
                }
            }
        }

        return new LlmResult(
            error: $lastError,
            latencyMs: (int) round((microtime(true) - $start) * 1000),
        );
    }

    private function isTransient(int $status): bool
    {
        return in_array($status, self::TRANSIENT_STATUSES, true);
    }

    /** Jeda backoff eksponensial + jitter (attempt mulai dari 1). */
    private function backoff(int $attempt): void
    {
        $baseMs = 700 * (2 ** ($attempt - 1)); // 700ms, 1400ms, ...
        $jitterMs = random_int(0, 300);
        usleep(($baseMs + $jitterMs) * 1000);
    }

    private function parseSuccess(array $data, array $model, int $latency): LlmResult
    {
        $text = $data['choices'][0]['message']['content'] ?? '';
        $usage = $data['usage'] ?? [];

        // Keluaran kosong = kegagalan yang harus terlihat (bukan sukses teks
        // kosong). Umum terjadi bila max_tokens habis dipakai reasoning atau
        // respons terpotong (finish_reason: length).
        if (trim((string) $text) === '') {
            $finish = $data['choices'][0]['finish_reason'] ?? 'tidak diketahui';
            return new LlmResult(
                error: "Model mengembalikan konten kosong (finish_reason: {$finish}). "
                    . 'Kemungkinan max_tokens habis untuk reasoning atau keluaran terpotong.',
                latencyMs: $latency,
            );
        }

        $cacheRead = (int) ($usage['prompt_tokens_details']['cached_tokens'] ?? 0);
        $promptTokens = (int) ($usage['prompt_tokens'] ?? 0);
        $fullInput = max(0, $promptTokens - $cacheRead);

        return new LlmResult(
            text: trim($text),
            inputTokens: $fullInput,
            outputTokens: (int) ($usage['completion_tokens'] ?? 0),
            cacheReadTokens: $cacheRead,
            cacheWriteTokens: 0,
            latencyMs: $latency,
            modelVersion: $data['model'] ?? $model['model'],
            raw: $usage,
        );
    }
}
