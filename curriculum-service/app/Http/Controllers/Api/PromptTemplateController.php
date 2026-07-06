<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AppliesSorting;
use App\Http\Controllers\Controller;
use App\Http\Resources\PromptTemplateResource;
use App\Models\PromptTemplate;
use App\Services\Ai\PromptRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Kelola PROMPT untuk UI. Menyajikan:
 *  - catalog(): daftar SLOT prompt bawaan (config/prompts.php) + status override
 *    aktif per slot (untuk tampilan "default vs override" di UI).
 *  - CRUD prompt_template: buat/ubah/hapus OVERRIDE (per-tenant/jenis_mk).
 *
 * Teks default tetap di config/prompts.php (aman, versioned). Override DB hanya
 * dibuat saat admin memang ingin mengubah; menghapus override = kembali default.
 */
class PromptTemplateController extends Controller
{
    use AppliesSorting;

    public function __construct(private PromptRepository $prompts) {}

    /** Katalog slot prompt bawaan + info override efektif (untuk UI). */
    public function catalog(Request $request): JsonResponse
    {
        $institusiId = $request->filled('institusi_id') ? $request->integer('institusi_id') : null;

        $slots = collect($this->prompts->slots())->map(function ($cfg, $slot) use ($institusiId) {
            $override = $this->prompts->override($slot, $institusiId, null);
            $efektif = $this->prompts->resolve($slot, $institusiId, null);

            return [
                'slot'           => $slot,
                'label'          => $cfg['label'] ?? $slot,
                'group'          => $cfg['group'] ?? 'lain',
                'default_system' => $cfg['system'] ?? '',
                'default_schema' => $cfg['schema'] ?? '',
                'sumber_efektif' => $efektif['sumber'],
                'override'       => $override ? new PromptTemplateResource($override) : null,
            ];
        })->values();

        return response()->json(['data' => $slots]);
    }

    /** Daftar semua override tersimpan (bisa difilter & diurut). */
    public function index(Request $request): JsonResponse
    {
        $query = PromptTemplate::query();

        if ($request->filled('jenis_output')) {
            $query->where('jenis_output', $request->string('jenis_output'));
        }
        if ($request->filled('jenis_mk')) {
            $query->where('jenis_mk', $request->string('jenis_mk'));
        }
        if ($request->filled('institusi_id')) {
            $query->where('institusi_id', $request->integer('institusi_id'));
        }
        if ($request->filled('aktif')) {
            $query->where('aktif', $request->boolean('aktif'));
        }

        $this->applySort(
            $query,
            $request,
            ['jenis_output', 'jenis_mk', 'versi', 'aktif', 'created_at'],
            'jenis_output',
        );

        return PromptTemplateResource::collection(
            $query->paginate($request->integer('per_page', 25)),
        )->response();
    }

    public function show(PromptTemplate $promptTemplate): PromptTemplateResource
    {
        return new PromptTemplateResource($promptTemplate);
    }

    /** Buat override baru. */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $template = PromptTemplate::create($data);

        return (new PromptTemplateResource($template))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, PromptTemplate $promptTemplate): PromptTemplateResource
    {
        $promptTemplate->update($this->validated($request, $promptTemplate));

        return new PromptTemplateResource($promptTemplate);
    }

    public function destroy(PromptTemplate $promptTemplate): JsonResponse
    {
        $promptTemplate->delete();

        return response()->json(['message' => 'Override prompt dihapus; slot kembali memakai default.']);
    }

    /** Validasi + normalisasi (skema_output diterima sebagai string JSON dari UI). */
    private function validated(Request $request, ?PromptTemplate $existing = null): array
    {
        $slots = array_keys($this->prompts->slots());

        $data = $request->validate([
            'jenis_output' => [$existing ? 'sometimes' : 'required', Rule::in($slots)],
            'jenis_mk'     => ['nullable', Rule::in(['murni', 'praktikum'])],
            'institusi_id' => ['nullable', 'integer', 'exists:institusi,id'],
            'sistem_prompt' => [$existing ? 'sometimes' : 'required', 'string', 'min:10'],
            'skema_output' => ['nullable', 'string'],
            'versi'        => ['nullable', 'integer', 'min:1'],
            'aktif'        => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('skema_output', $data)) {
            $data['skema_output'] = $this->parseSchema($data['skema_output']);
        }

        return $data;
    }

    /** Terima string JSON dari UI -> array (untuk cast model). Kosong => null. */
    private function parseSchema(?string $raw): ?array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        abort_if(json_last_error() !== JSON_ERROR_NONE, 422, 'skema_output harus JSON valid.');

        return is_array($decoded) ? $decoded : null;
    }
}
