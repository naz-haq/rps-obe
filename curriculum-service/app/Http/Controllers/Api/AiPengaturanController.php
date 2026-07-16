<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiPengaturan;
use App\Services\Ai\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Pengaturan AI untuk UI: baca/ubah PROFIL AI aktif (produksi/simulasi) dan
 * lihat katalog model + profil tersedia. Peralihan jalur AI dilakukan di sini
 * TANPA menyentuh kode (menulis baris AI_PENGATURAN global/tenant).
 */
class AiPengaturanController extends Controller
{
    public function __construct(private AiService $ai) {}

    /** Profil efektif + daftar profil & pemetaan model per-tugas (untuk UI). */
    public function show(Request $request): JsonResponse
    {
        $institusiId = $request->filled('institusi_id') ? $request->integer('institusi_id') : null;

        $profiles = config('ai.profiles', []);
        $availability = $this->ai->providerAvailability($institusiId);

        $models = collect(config('ai.models', []))
            ->map(fn($m, $key) => [
                'key'      => $key,
                'provider' => $m['provider'],
                'model'    => $m['model'],
                'pricing'  => $m['pricing'] ?? null,
                'tersedia' => (bool) ($availability[$m['provider']] ?? false),
            ])->values();

        // Metadata task (label + default + aturan lintas-provider) untuk UI.
        $tasks = collect(config('ai.tasks', []))
            ->map(fn($cfg, $key) => [
                'key'               => $key,
                'label'             => self::TASK_LABEL[$key] ?? $key,
                'default_model'     => $cfg['model'] ?? null,
                'cross_provider_of' => $cfg['cross_provider_of'] ?? null,
            ])->values();

        $globalOverride = (array) (AiPengaturan::whereNull('institusi_id')->value('model_override') ?? []);

        return response()->json([
            'data' => [
                'profil_aktif'     => $this->ai->activeProfile($institusiId),
                'default_env'      => (string) config('ai.active_profile'),
                'global_tersimpan' => AiPengaturan::whereNull('institusi_id')->value('profil'),
                'tenant_tersimpan' => $institusiId
                    ? AiPengaturan::where('institusi_id', $institusiId)->value('profil')
                    : null,
                'profil_tersedia'  => array_keys($profiles),
                'profiles'         => $profiles,
                'providers'        => array_keys(config('ai.providers', [])),
                'ketersediaan'     => $availability,
                'models'           => $models,
                'tasks'            => $tasks,
                'model_override'   => $globalOverride,
                'model_efektif'    => $this->ai->effectiveModelMap($institusiId),
            ],
        ]);
    }

    /**
     * Daftar model LIVE per-provider (ditarik dari API key aktif). Dipakai UI
     * agar pengguna bisa memilih model APA SAJA yang tersedia di provider,
     * bukan sekadar katalog statik. Aman: provider tanpa key/gagal → tak muncul.
     */
    public function modelsLive(Request $request): JsonResponse
    {
        $institusiId = $request->filled('institusi_id') ? $request->integer('institusi_id') : null;

        return response()->json(['data' => $this->ai->liveModels($institusiId)]);
    }

    /** Set profil aktif (global bila institusi_id kosong, atau per-tenant). */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'profil'       => ['required', Rule::in(array_keys(config('ai.profiles', [])))],
            'institusi_id' => ['nullable', 'integer', 'exists:institusi,id'],
            'diubah_oleh'  => ['nullable', 'integer'],
        ]);

        $record = AiPengaturan::updateOrCreate(
            ['institusi_id' => $data['institusi_id'] ?? null],
            ['profil' => $data['profil'], 'diubah_oleh' => $data['diubah_oleh'] ?? null],
        );

        return response()->json([
            'message' => "Profil AI aktif kini '{$data['profil']}'.",
            'data'    => [
                'pengaturan'   => $record,
                'profil_aktif' => $this->ai->activeProfile($data['institusi_id'] ?? null),
            ],
        ], $record->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Simpan override model per-tugas (manual dari UI). Nilai null/'' pada suatu
     * task = hapus override (ikut profil). Menegakkan prinsip:
     *  - model harus ada di katalog & provider-nya punya API key,
     *  - task validator wajib beda provider dari task yang divalidasinya.
     */
    public function updateModel(Request $request): JsonResponse
    {
        $taskKeys = array_keys((array) config('ai.tasks', []));
        $modelKeys = array_keys((array) config('ai.models', []));

        $data = $request->validate([
            'institusi_id'     => ['nullable', 'integer', 'exists:institusi,id'],
            'diubah_oleh'      => ['nullable', 'integer'],
            'model_override'   => ['present', 'array'],
            'model_override.*' => ['nullable', 'string'],
        ]);

        $institusiId = $data['institusi_id'] ?? null;
        $availability = $this->ai->providerAvailability($institusiId);

        // Bersihkan: hanya task dikenal, buang nilai kosong, validasi model & key.
        // Model bisa berupa KEY katalog ATAU "provider::model-id" (model LIVE).
        $bersih = [];
        foreach ($data['model_override'] as $task => $modelKey) {
            if (! in_array($task, $taskKeys, true)) {
                continue;
            }
            if ($modelKey === null || $modelKey === '') {
                continue; // kosong = ikut profil
            }

            if (str_contains($modelKey, '::')) {
                [$provider, $apiModel] = explode('::', $modelKey, 2);
                if ($apiModel === '' || ! config("ai.providers.$provider")) {
                    throw ValidationException::withMessages([
                        "model_override.$task" => "Model live '{$modelKey}' tidak valid.",
                    ]);
                }
            } elseif (in_array($modelKey, $modelKeys, true)) {
                $provider = config("ai.models.$modelKey.provider");
            } else {
                throw ValidationException::withMessages([
                    "model_override.$task" => "Model '{$modelKey}' tidak ada di katalog.",
                ]);
            }

            if (! ($availability[$provider] ?? false)) {
                throw ValidationException::withMessages([
                    "model_override.$task" => "Provider '{$provider}' tidak punya API key aktif; model '{$modelKey}' belum bisa dipakai.",
                ]);
            }
            $bersih[$task] = $modelKey;
        }

        // Peta override PROSPEKTIF (belum disimpan): untuk skop global = $bersih;
        // untuk tenant = override global (DB) ditimpa $bersih.
        $prospektif = $bersih;
        if ($institusiId) {
            $globalOverride = array_filter(
                (array) (AiPengaturan::whereNull('institusi_id')->value('model_override') ?? []),
                fn($v) => is_string($v) && $v !== '',
            );
            $prospektif = array_merge($globalOverride, $bersih);
        }

        // Hitung peta model efektif dari data in-memory (TANPA menyimpan), lalu
        // tegakkan aturan lintas-provider SEBELUM commit ke DB.
        $profil = $this->ai->activeProfile($institusiId);
        $profilMap = (array) config("ai.profiles.$profil", []);
        $providerUntuk = function (string $task) use ($prospektif, $profilMap): ?string {
            $model = $prospektif[$task]
                ?? ($profilMap[$task] ?? config("ai.tasks.$task.model"));

            return str_contains((string) $model, '::')
                ? explode('::', (string) $model, 2)[0]
                : config("ai.models.$model.provider");
        };

        foreach ($taskKeys as $task) {
            $lawan = config("ai.tasks.$task.cross_provider_of");
            if ($lawan) {
                $p1 = $providerUntuk($task);
                $p2 = $providerUntuk($lawan);
                if ($p1 !== null && $p1 === $p2) {
                    throw ValidationException::withMessages([
                        "model_override.$task" => "Task '{$task}' wajib beda provider dari '{$lawan}' (anti-halusinasi), tetapi keduanya kini '{$p1}'.",
                    ]);
                }
            }
        }

        // Lolos validasi → simpan.
        $record = AiPengaturan::firstOrNew(['institusi_id' => $institusiId]);
        $record->model_override = $bersih ?: null;
        if (! $record->profil) {
            $record->profil = $profil;
        }
        if (array_key_exists('diubah_oleh', $data)) {
            $record->diubah_oleh = $data['diubah_oleh'];
        }
        $record->save();

        return response()->json([
            'message' => 'Pemilihan model per-tugas tersimpan.',
            'data'    => [
                'model_override' => $record->model_override ?? [],
                'model_efektif'  => $this->ai->effectiveModelMap($institusiId),
            ],
        ]);
    }

    /** Label task untuk UI. */
    private const TASK_LABEL = [
        'generate'       => 'Generate RPS',
        'judge'          => 'Judge / QA',
        'validator'      => 'Validator anti-halusinasi',
        'asistif'        => 'Asistif inline',
        'ekstraksi'      => 'Ekstraksi / klasifikasi',
        'konversasional' => 'Konversasional',
        'eskalasi'       => 'Eskalasi',
    ];
}
