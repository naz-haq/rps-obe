<?php

namespace App\Services\Ai;

use App\Models\AiInteraksi;
use App\Models\AiKredensial;
use App\Models\AiPengaturan;
use App\Services\Ai\Exceptions\AiBudgetException;
use InvalidArgumentException;

/**
 * Orkestrator AI task-aware untuk Curriculum Service.
 *
 * Tanggung jawab:
 *  - Memetakan "task" (generate/judge/validator/asistif/ekstraksi/eskalasi)
 *    ke model + parameter sesuai config/ai.php (Blueprint 7.6).
 *  - Menyelesaikan kredensial BYOK per tenant dari AI_KREDENSIAL (fallback env,
 *    lalu mock saat dev).
 *  - Menjaga aturan lintas-provider untuk validator anti-halusinasi.
 *  - Menegakkan anggaran (kuota biaya) tenant sebelum memanggil model.
 *  - Mencatat setiap panggilan ke AI_INTERAKSI (token + biaya USD).
 */
class AiService
{
    public function __construct(
        private DriverManager $drivers,
        private CostCalculator $cost,
    ) {}

    /**
     * Jalankan satu tugas AI.
     *
     * @param array $context institusi_id (wajib), user_id, entity_type,
     *                       entity_id, model (override key katalog), model_name,
     *                       temperature, max_tokens, mode (label log)
     */
    public function run(string $task, string $system, string $prompt, array $context = []): AiOutcome
    {
        $taskCfg = config("ai.tasks.$task");
        if (! $taskCfg) {
            throw new InvalidArgumentException("Task AI tidak dikenal: {$task}");
        }

        $institusiId = $context['institusi_id'] ?? null;
        $userId = $context['user_id'] ?? null;

        // Pemilihan model: override eksplisit > profil aktif (produksi/simulasi) > default task.
        $modelKey = $context['model'] ?? $this->resolveModelKey($task, $institusiId);
        $modelCfg = config("ai.models.$modelKey");
        if (! $modelCfg) {
            throw new InvalidArgumentException("Model AI tidak dikenal: {$modelKey}");
        }

        $this->assertCrossProvider($task, $modelCfg['provider'], $institusiId);

        $cred = $this->resolveCredentials($modelCfg, $institusiId, $userId, $context);

        $this->assertBudget($cred['kredensial'] ?? null, $institusiId);

        $params = [
            'temperature' => $context['temperature'] ?? $taskCfg['temperature'] ?? config('ai.default_params.temperature'),
            'max_tokens'  => $context['max_tokens'] ?? $taskCfg['max_tokens'] ?? config('ai.default_params.max_tokens'),
        ];

        $driver = $this->drivers->make($cred['driver']);
        $result = $driver->run($cred['model_array'], $system, $prompt, $params);

        // Fallback RUNTIME ke mock: bila provider NYATA gagal (mis. kuota gratis
        // Gemini Flash-Lite habis -> 503/429 setelah retry) dan fallback_to_mock
        // aktif, ulangi lewat MockDriver agar alur (CPMK/Sub-CPMK/matriks/RPS)
        // tetap selesai tanpa biaya saat pengembangan. Beda dengan fallback
        // kredensial di resolveCredentials() yang hanya menangani ketiadaan key.
        if ($result->failed() && $cred['provider'] !== 'mock' && config('ai.fallback_to_mock')) {
            $cred = $this->mockCredentials();
            $driver = $this->drivers->make('mock');
            $result = $driver->run($cred['model_array'], $system, $prompt, $params);
        }

        // Biaya dihitung dari harga efektif yang benar-benar dijalankan
        // (0 bila fallback ke mock, harga model bila provider nyata).
        $biaya = $this->cost->usd($cred['model_array']['pricing'], $result);

        $interaksi = $this->log($task, $cred, $result, $biaya, $context);

        return new AiOutcome($result, $biaya, $interaksi);
    }

    /**
     * Validator harus memakai provider berbeda dari tugas yang divalidasinya.
     * Sadar-profil: model pembanding diselesaikan lewat profil aktif juga.
     */
    private function assertCrossProvider(string $task, string $provider, ?int $institusiId): void
    {
        $other = config("ai.tasks.$task.cross_provider_of");
        if (! $other) {
            return;
        }

        $otherModel = $this->resolveModelKey($other, $institusiId);
        $otherProvider = config("ai.models.$otherModel.provider");

        if ($otherProvider !== null && $otherProvider === $provider) {
            throw new InvalidArgumentException(
                "Task '{$task}' wajib lintas-provider dari '{$other}', tetapi keduanya memakai provider '{$provider}'."
            );
        }
    }

    /**
     * Key model efektif untuk sebuah task: profil aktif menimpa default task.
     */
    private function resolveModelKey(string $task, ?int $institusiId): string
    {
        $profil = $this->activeProfile($institusiId);
        $map = config("ai.profiles.$profil", []);

        return $map[$task] ?? config("ai.tasks.$task.model");
    }

    /**
     * Profil AI aktif: baris AI_PENGATURAN tenant > baris global (institusi null)
     * > config('ai.active_profile') (dari env). Memungkinkan peralihan via UI.
     */
    public function activeProfile(?int $institusiId): string
    {
        if ($institusiId) {
            $tenant = AiPengaturan::where('institusi_id', $institusiId)->value('profil');
            if ($tenant) {
                return $tenant;
            }
        }

        $global = AiPengaturan::whereNull('institusi_id')->value('profil');

        return $global ?: (string) config('ai.active_profile', 'produksi');
    }

    /**
     * Susun kredensial efektif: BYOK tenant > env server > mock (dev).
     *
     * @return array{driver:string, provider:string, model:string, kredensial:?AiKredensial, model_array:array}
     */
    private function resolveCredentials(array $modelCfg, ?int $institusiId, ?int $userId, array $context): array
    {
        $provider = $modelCfg['provider'];
        $providerCfg = config("ai.providers.$provider");
        if (! $providerCfg) {
            throw new InvalidArgumentException("Provider AI tidak dikonfigurasi: {$provider}");
        }

        $kredensial = null;
        $apiKey = $providerCfg['api_key'] ?? null;
        $apiModel = $context['model_name'] ?? $modelCfg['model'];

        if ($institusiId && $provider !== 'mock') {
            $kredensial = AiKredensial::query()
                ->where('institusi_id', $institusiId)
                ->where('provider', $provider)
                ->where('aktif', true)
                ->when($userId, fn($q) => $q->where(function ($qq) use ($userId) {
                    $qq->where('user_id', $userId)->orWhereNull('user_id');
                }))
                ->when(! $userId, fn($q) => $q->whereNull('user_id'))
                ->orderByRaw('user_id IS NULL') // kredensial spesifik user diprioritaskan
                ->first();

            if ($kredensial) {
                $apiKey = $kredensial->api_key_encrypted; // cast 'encrypted' -> plaintext
                $apiModel = $context['model_name'] ?? $kredensial->model_default ?? $modelCfg['model'];
            }
        }

        // Tak ada kredensial nyata -> mock (dev) atau gagal jelas.
        if ($provider !== 'mock' && empty($apiKey)) {
            if (config('ai.fallback_to_mock')) {
                return $this->mockCredentials();
            }

            throw new InvalidArgumentException(
                "Tidak ada kredensial AI untuk provider '{$provider}' (BYOK tenant maupun env server)."
            );
        }

        return [
            'driver'      => $providerCfg['driver'],
            'provider'    => $provider,
            'model'       => $apiModel,
            'kredensial'  => $kredensial,
            'model_array' => [
                'api_key'  => $apiKey,
                'base_url' => $providerCfg['base_url'],
                'model'    => $apiModel,
                'provider' => $provider,
                'pricing'  => $modelCfg['pricing'],
            ],
        ];
    }

    /**
     * Kredensial driver mock (dev/simulasi): dipakai saat tak ada key nyata
     * ATAU sebagai fallback runtime ketika provider nyata gagal (kuota habis).
     *
     * @return array{driver:string, provider:string, model:string, kredensial:?AiKredensial, model_array:array}
     */
    private function mockCredentials(): array
    {
        $mock = config('ai.models.mock');

        return [
            'driver'      => 'mock',
            'provider'    => 'mock',
            'model'       => $mock['model'],
            'kredensial'  => null,
            'model_array' => [
                'api_key'  => 'local',
                'base_url' => null,
                'model'    => $mock['model'],
                'provider' => 'mock',
                'pricing'  => $mock['pricing'],
            ],
        ];
    }

    /**
     * Tegakkan kuota biaya tenant (jumlah biaya AI_INTERAKSI vs anggaran).
     */
    private function assertBudget(?AiKredensial $kredensial, ?int $institusiId): void
    {
        if (! $kredensial || $kredensial->anggaran === null || ! $institusiId) {
            return;
        }

        $terpakai = (float) AiInteraksi::where('institusi_id', $institusiId)->sum('biaya');

        if ($terpakai >= (float) $kredensial->anggaran) {
            throw new AiBudgetException(
                "Anggaran AI tenant terlampaui: terpakai \${$terpakai} dari kuota \${$kredensial->anggaran}."
            );
        }
    }

    private function log(string $task, array $cred, LlmResult $result, float $biaya, array $context): AiInteraksi
    {
        return AiInteraksi::create([
            'institusi_id' => $context['institusi_id'] ?? null,
            'user_id'      => $context['user_id'] ?? null,
            'entity_type'  => $context['entity_type'] ?? null,
            'entity_id'    => $context['entity_id'] ?? null,
            'mode'         => $context['mode'] ?? $task,
            'provider'     => $cred['provider'],
            'model'        => $cred['model'],
            'prompt'       => $context['log_prompt'] ?? null,
            'response'     => $result->failed() ? null : $result->text,
            'tokens_in'    => $result->inputTokens + $result->cacheReadTokens,
            'tokens_out'   => $result->outputTokens,
            'biaya'        => round($biaya, 6),
            'status'       => $result->failed() ? 'gagal' : 'sukses',
        ]);
    }
}
