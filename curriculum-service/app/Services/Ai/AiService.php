<?php

namespace App\Services\Ai;

use App\Models\AiInteraksi;
use App\Models\AiKredensial;
use App\Models\AiPengaturan;
use App\Services\Ai\Exceptions\AiBudgetException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
        $modelCfg = $this->modelCfgFor($modelKey);
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
        $otherProvider = $this->providerOf($otherModel);

        if ($otherProvider !== null && $otherProvider === $provider) {
            throw new InvalidArgumentException(
                "Task '{$task}' wajib lintas-provider dari '{$other}', tetapi keduanya memakai provider '{$provider}'."
            );
        }
    }

    /**
     * Key model efektif untuk sebuah task: override manual per-tugas menimpa
     * profil aktif, yang menimpa default task.
     */
    private function resolveModelKey(string $task, ?int $institusiId): string
    {
        $override = $this->modelOverrideMap($institusiId);
        if (! empty($override[$task]) && $this->modelCfgFor($override[$task])) {
            return $override[$task];
        }

        $profil = $this->activeProfile($institusiId);
        $map = config("ai.profiles.$profil", []);

        return $map[$task] ?? config("ai.tasks.$task.model");
    }

    /**
     * Provider dari referensi model. Mendukung DUA bentuk:
     *  - key katalog config('ai.models.<key>') → provider dari katalog,
     *  - "provider::model-id" (model LIVE dari API) → prefix sebelum '::'.
     */
    private function providerOf(string $modelRef): ?string
    {
        if (str_contains($modelRef, '::')) {
            return explode('::', $modelRef, 2)[0];
        }

        return config("ai.models.$modelRef.provider");
    }

    /**
     * Konfigurasi model efektif (provider/model API/pricing) dari sebuah
     * referensi. Model katalog memakai harga katalog; model LIVE "provider::id"
     * dibangun on-the-fly dengan harga 0 (provider yang harus terdaftar).
     *
     * @return array{provider:string, model:string, pricing:array}|null
     */
    private function modelCfgFor(string $modelRef): ?array
    {
        if (str_contains($modelRef, '::')) {
            [$provider, $apiModel] = explode('::', $modelRef, 2);
            if ($apiModel === '' || ! config("ai.providers.$provider")) {
                return null;
            }

            // Gemini (lapisan kompatibel-OpenAI) menolak prefix 'models/' pada
            // endpoint chat — pakai id telanjang.
            if ($provider === 'gemini' && str_starts_with($apiModel, 'models/')) {
                $apiModel = substr($apiModel, strlen('models/'));
            }

            return [
                'provider' => $provider,
                'model'    => $apiModel,
                'pricing'  => ['input' => 0.0, 'output' => 0.0, 'cache_read' => 0.0, 'cache_write' => 0.0],
            ];
        }

        return config("ai.models.$modelRef");
    }

    /**
     * Peta override model per-tugas efektif: baris tenant menimpa baris global
     * (per-key). Task tanpa override tidak muncul di peta.
     *
     * @return array<string,string>
     */
    public function modelOverrideMap(?int $institusiId): array
    {
        $global = (array) (AiPengaturan::whereNull('institusi_id')->value('model_override') ?? []);

        $tenant = [];
        if ($institusiId) {
            $tenant = (array) (AiPengaturan::where('institusi_id', $institusiId)->value('model_override') ?? []);
        }

        // Buang nilai kosong (null/'') agar jatuh ke profil.
        return array_filter(array_merge($global, $tenant), fn($v) => is_string($v) && $v !== '');
    }

    /**
     * Peta model efektif untuk SEMUA task (task => {model, provider, sumber}).
     * Dipakai UI pengaturan & validasi lintas-provider saat menyimpan.
     *
     * @return array<string,array{model:string,provider:?string,sumber:string}>
     */
    public function effectiveModelMap(?int $institusiId): array
    {
        $override = $this->modelOverrideMap($institusiId);
        $profil = $this->activeProfile($institusiId);
        $profilMap = config("ai.profiles.$profil", []);

        $out = [];
        foreach (array_keys((array) config('ai.tasks', [])) as $task) {
            if (! empty($override[$task]) && $this->modelCfgFor($override[$task])) {
                $model = $override[$task];
                $sumber = 'override';
            } elseif (! empty($profilMap[$task])) {
                $model = $profilMap[$task];
                $sumber = 'profil';
            } else {
                $model = config("ai.tasks.$task.model");
                $sumber = 'default';
            }

            $out[$task] = [
                'model'    => $model,
                'provider' => $this->providerOf($model),
                'sumber'   => $sumber,
            ];
        }

        return $out;
    }

    /**
     * Daftar model LIVE per-provider, diambil langsung dari endpoint
     * kompatibel-OpenAI `GET {base_url}/models` memakai API key aktif
     * (env server atau BYOK tenant). Hasil di-cache 30 menit per provider.
     * Provider tanpa key / non-OpenAI-compatible / gagal diambil → dilewati.
     *
     * @return array<string,array<int,string>> provider => [model-id, ...]
     */
    public function liveModels(?int $institusiId = null): array
    {
        $out = [];
        foreach (array_keys((array) config('ai.providers', [])) as $provider) {
            if ($provider === 'mock') {
                continue;
            }
            $conn = $this->providerConnection($provider, $institusiId);
            // Hanya driver kompatibel-OpenAI yang punya endpoint /models seragam.
            if (! $conn || ($conn['driver'] ?? null) !== 'openai' || empty($conn['base_url'])) {
                continue;
            }
            $models = $this->fetchProviderModels($provider, $conn);
            if (! empty($models)) {
                $out[$provider] = $models;
            }
        }

        return $out;
    }

    /**
     * Resolusi koneksi provider (base_url + api_key + driver): env server dulu,
     * lalu BYOK tenant aktif. Mengembalikan null bila tak ada key.
     *
     * @return array{base_url:?string, api_key:string, driver:string}|null
     */
    private function providerConnection(string $provider, ?int $institusiId): ?array
    {
        $cfg = config("ai.providers.$provider");
        if (! $cfg) {
            return null;
        }

        $apiKey = $cfg['api_key'] ?? null;
        if (empty($apiKey) && $institusiId) {
            $kred = AiKredensial::where('institusi_id', $institusiId)
                ->where('provider', $provider)
                ->where('aktif', true)
                ->first();
            if ($kred) {
                $apiKey = $kred->api_key_encrypted; // cast 'encrypted' -> plaintext
            }
        }

        if (empty($apiKey)) {
            return null;
        }

        return [
            'base_url' => $cfg['base_url'] ?? null,
            'api_key'  => $apiKey,
            'driver'   => $cfg['driver'] ?? 'openai',
        ];
    }

    /**
     * Ambil & cache daftar id model dari `GET {base_url}/models`. Aman terhadap
     * error jaringan/timeout (kembalikan array kosong agar UI tetap jalan).
     *
     * @return array<int,string>
     */
    private function fetchProviderModels(string $provider, array $conn): array
    {
        $cacheKey = "ai:models:live:$provider:" . md5(((string) $conn['base_url']) . '|' . substr((string) $conn['api_key'], -8));

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($provider, $conn): array {
            try {
                // GitHub Models tidak menyediakan /models ala OpenAI; daftar model
                // ada di /catalog/models (array {id,...} tingkat atas).
                if ($provider === 'github') {
                    return $this->fetchGithubCatalog($conn);
                }

                $url = rtrim((string) $conn['base_url'], '/') . '/models';
                $resp = Http::withToken($conn['api_key'])->timeout(15)->get($url);
                if (! $resp->successful()) {
                    return [];
                }

                $data = $resp->json('data');
                if (! is_array($data)) {
                    return [];
                }

                return collect($data)
                    ->map(fn($m) => is_array($m) ? ($m['id'] ?? null) : null)
                    ->filter(fn($id) => is_string($id) && $id !== '')
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();
            } catch (\Throwable) {
                return [];
            }
        });
    }

    /**
     * Daftar id model GitHub Models dari `GET {host}/catalog/models`
     * (respons = array {id, ...} tingkat atas, bukan {data:[]}).
     *
     * @param  array{base_url:?string, api_key:string, driver:string}  $conn
     * @return array<int,string>
     */
    private function fetchGithubCatalog(array $conn): array
    {
        // base_url = https://models.github.ai/inference → katalog di /catalog/models.
        $host = preg_replace('#/inference/?$#', '', rtrim((string) $conn['base_url'], '/'));
        $resp = Http::withToken($conn['api_key'])->timeout(15)->get($host . '/catalog/models');
        if (! $resp->successful()) {
            return [];
        }

        $data = $resp->json();
        if (! is_array($data)) {
            return [];
        }

        return collect($data)
            ->map(fn($m) => is_array($m) ? ($m['id'] ?? null) : null)
            ->filter(fn($id) => is_string($id) && $id !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Ketersediaan tiap provider (punya API key: env server atau BYOK tenant).
     *
     * @return array<string,bool>
     */
    public function providerAvailability(?int $institusiId = null): array
    {
        $out = [];
        foreach ((array) config('ai.providers', []) as $name => $cfg) {
            if ($name === 'mock') {
                $out[$name] = true;
                continue;
            }
            $ada = ! empty($cfg['api_key'] ?? null);
            if (! $ada && $institusiId) {
                $ada = AiKredensial::where('institusi_id', $institusiId)
                    ->where('provider', $name)
                    ->where('aktif', true)
                    ->exists();
            }
            $out[$name] = $ada;
        }

        return $out;
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
