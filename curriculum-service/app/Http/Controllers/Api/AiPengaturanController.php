<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiPengaturan;
use App\Services\Ai\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        $models = collect(config('ai.models', []))
            ->map(fn($m, $key) => [
                'key'      => $key,
                'provider' => $m['provider'],
                'model'    => $m['model'],
                'pricing'  => $m['pricing'] ?? null,
            ])->values();

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
                'models'           => $models,
            ],
        ]);
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
}
