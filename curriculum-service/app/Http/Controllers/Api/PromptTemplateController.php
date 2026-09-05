<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AppliesSorting;
use App\Http\Controllers\Controller;
use App\Http\Resources\PromptTemplateResource;
use App\Models\Institusi;
use App\Models\PromptTemplate;
use App\Services\Ai\PromptRepository;
use App\Services\Governance\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Kelola PROMPT untuk UI. Menyajikan:
 *  - catalog(): daftar SLOT prompt bawaan (config/prompts.php) + status override
 *    aktif per slot (untuk tampilan "default vs override" di UI).
 *  - CRUD prompt_template: buat/ubah/hapus OVERRIDE (per-tenant/jenis_mk).
 *
 * Mutasi berversikan dan diserialkan per tenant. Reset menyimpan penanda default
 * tanpa menghapus riwayat atau memunculkan kembali override global/versi lama.
 */
class PromptTemplateController extends Controller
{
    use AppliesSorting;

    public function __construct(private PromptRepository $prompts) {}

    /** Katalog slot prompt bawaan + info override efektif (untuk UI). */
    public function catalog(Request $request): JsonResponse
    {
        $selection = $request->validate(['jenis_mk' => ['nullable', Rule::in(['murni', 'praktikum'])]]);
        $institusiId = $this->tenantId($request);
        $jenisMk = $selection['jenis_mk'] ?? null;

        $slots = collect($this->prompts->slots())->map(function ($cfg, $slot) use ($institusiId, $jenisMk) {
            $efektif = $this->prompts->resolve($slot, $institusiId, $jenisMk);
            $selected = $efektif['template_id'] ? PromptTemplate::find($efektif['template_id']) : null;
            $override = $selected && ! $selected->use_default ? $selected : null;

            return [
                'slot'           => $slot,
                'label'          => $cfg['label'] ?? $slot,
                'group'          => $cfg['group'] ?? 'lain',
                'default_system' => $cfg['system'] ?? '',
                'default_schema' => $cfg['schema'] ?? '',
                'jenis_mk'       => $jenisMk,
                'institusi_id'   => $institusiId,
                'effective_system' => $efektif['system'],
                'effective_schema' => $efektif['schema'],
                'sumber_efektif' => $efektif['sumber'],
                'selection'      => $selected ? [
                    'id' => $selected->id,
                    'versi' => $selected->versi,
                    'institusi_id' => $selected->institusi_id,
                    'jenis_mk' => $selected->jenis_mk,
                    'use_default' => $selected->use_default,
                ] : null,
                // Inherited overrides must be copied into the selected scope, not edited there.
                'can_edit'       => $override !== null
                    && $override->institusi_id === $institusiId && $override->jenis_mk === $jenisMk,
                'override'       => $override ? new PromptTemplateResource($override) : null,
            ];
        })->values();

        return response()->json(['data' => $slots]);
    }

    /** Daftar semua override tersimpan (bisa difilter & diurut). */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'jenis_output' => ['sometimes', Rule::in(array_keys($this->prompts->slots()))],
            'jenis_mk' => ['nullable', Rule::in(['murni', 'praktikum'])],
        ]);
        $query = PromptTemplate::query()->where('institusi_id', $this->tenantId($request));

        if ($request->filled('jenis_output')) {
            $query->where('jenis_output', $request->string('jenis_output'));
        }
        if ($request->filled('jenis_mk')) {
            $query->where('jenis_mk', $request->string('jenis_mk'));
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

    public function show(Request $request, PromptTemplate $promptTemplate): PromptTemplateResource
    {
        $this->authorizeTemplate($request, $promptTemplate);
        return new PromptTemplateResource($promptTemplate);
    }

    /** Buat override baru. */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $tenantId = $this->tenantId($request);
        $template = $this->serialized($tenantId, fn() => $this->appendVersion(
            $tenantId,
            $data['jenis_output'],
            $data['jenis_mk'] ?? null,
            $data,
        ));

        return (new PromptTemplateResource($template))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, PromptTemplate $promptTemplate): JsonResponse
    {
        $this->authorizeTemplate($request, $promptTemplate);
        $data = $this->validated($request, $promptTemplate);
        $template = $this->serialized($promptTemplate->institusi_id, function () use ($promptTemplate, $data) {
            $this->assertCurrent($promptTemplate);
            return $this->appendVersion(
                $promptTemplate->institusi_id,
                $promptTemplate->jenis_output,
                $promptTemplate->jenis_mk,
                array_replace($promptTemplate->only(['sistem_prompt', 'skema_output', 'few_shot', 'aktif']), $data),
            );
        });

        // Versi baru dibuat, tetapi bagi klien ini tetap PUT yang sukses (200, bukan 201).
        return (new PromptTemplateResource($template))->response()->setStatusCode(200);
    }

    public function destroy(Request $request, PromptTemplate $promptTemplate): JsonResponse
    {
        $this->authorizeTemplate($request, $promptTemplate);
        $this->serialized($promptTemplate->institusi_id, function () use ($request, $promptTemplate) {
            $this->assertCurrent($promptTemplate);
            $this->resetScope($request, $promptTemplate->institusi_id, $promptTemplate->jenis_output, $promptTemplate->jenis_mk);
        });

        return response()->json(['message' => 'Prompt dikembalikan ke default kode; riwayat override dipertahankan.']);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slot' => ['required', Rule::in(array_keys($this->prompts->slots()))],
            'jenis_mk' => ['nullable', Rule::in(['murni', 'praktikum'])],
        ]);
        $tenantId = $this->tenantId($request);
        $marker = $this->serialized($tenantId, fn() => $this->resetScope(
            $request,
            $tenantId,
            $data['slot'],
            $data['jenis_mk'] ?? null,
        ));

        return response()->json([
            'message' => 'Prompt dikembalikan ke default kode; riwayat override dipertahankan.',
            'data' => new PromptTemplateResource($marker),
        ]);
    }

    private function resetScope(Request $request, ?int $tenantId, string $slot, ?string $jenisMk): PromptTemplate
    {
        $marker = $this->appendVersion($tenantId, $slot, $jenisMk, [
            'use_default' => true,
            'sistem_prompt' => '',
            'skema_output' => null,
            'aktif' => true,
        ]);
        AuditLogger::catat('prompt.reset', 'prompt_template', $marker->id, [
            'slot' => $slot,
            'jenis_mk' => $jenisMk,
            'versi' => $marker->versi,
        ], $tenantId, $request->user()?->id, $request->user()?->name);

        return $marker;
    }

    private function tenantId(Request $request): ?int
    {
        $authenticatedTenant = $request->user()?->institusi_id;
        $allowed = $request->attributes->get('tenant_institusi_ids');
        abort_if($authenticatedTenant !== null && $request->filled('institusi_id')
            && ! in_array($request->integer('institusi_id'), (array) $allowed, true), 403);
        $request->validate(['institusi_id' => ['nullable', 'integer', 'exists:institusi,id']]);

        return $request->filled('institusi_id') ? $request->integer('institusi_id')
            : ($authenticatedTenant !== null ? (int) $authenticatedTenant : null);
    }

    private function authorizeTemplate(Request $request, PromptTemplate $template): void
    {
        // Middleware blocks foreign tenants but intentionally allows global models;
        // global prompt mutations need this stricter, prompt-local guard.
        $tenantId = $this->tenantId($request);
        abort_if(
            $tenantId !== null && $template->institusi_id !== $tenantId,
            403,
            'Prompt global atau institusi lain tidak dapat diubah dari konteks ini.'
        );
    }

    private function serialized(?int $tenantId, callable $callback): mixed
    {
        return DB::transaction(function () use ($tenantId, $callback) {
            if ($tenantId !== null) {
                Institusi::whereKey($tenantId)->lockForUpdate()->firstOrFail();
            } else {
                // A pre-existing, deterministic row also serializes an empty global scope.
                abort_unless(DB::table('prompt_template_locks')->where('id', 1)->lockForUpdate()->first(), 503);
            }
            return $callback();
        }, 3);
    }

    private function scope(?int $tenantId, string $slot, ?string $jenisMk): Builder
    {
        return PromptTemplate::query()->where('institusi_id', $tenantId)
            ->where('jenis_output', $slot)->where('jenis_mk', $jenisMk);
    }

    /** Must be called under serialized(): all writers share the same lock. */
    private function appendVersion(?int $tenantId, string $slot, ?string $jenisMk, array $data): PromptTemplate
    {
        $scope = $this->scope($tenantId, $slot, $jenisMk);
        $version = (int) (clone $scope)->max('versi') + 1;
        (clone $scope)->where('aktif', true)->update(['aktif' => false]);

        return PromptTemplate::create(array_replace($data, [
            'institusi_id' => $tenantId,
            'jenis_output' => $slot,
            'jenis_mk' => $jenisMk,
            'versi' => $version,
            'aktif' => $data['aktif'] ?? true,
            'use_default' => $data['use_default'] ?? false,
        ]));
    }

    private function assertCurrent(PromptTemplate $template): void
    {
        $latest = $this->scope($template->institusi_id, $template->jenis_output, $template->jenis_mk)
            ->orderByDesc('versi')->orderByDesc('id')->first();
        abort_if(
            $latest?->id !== $template->id || $template->use_default,
            409,
            'Versi ini sudah menjadi riwayat atau penanda default. Muat ulang dan buat override baru.'
        );
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
            'versi'        => ['prohibited'],
            'use_default' => ['prohibited'],
            'aktif'        => ['nullable', 'boolean'],
        ]);

        if ($existing) {
            foreach (['jenis_output', 'jenis_mk', 'institusi_id'] as $key) {
                if (array_key_exists($key, $data) && $data[$key] != $existing->getAttribute($key)) {
                    throw ValidationException::withMessages([$key => 'Konteks versi tidak dapat dipindah. Buat override baru.']);
                }
            }
        }
        unset($data['institusi_id']); // Never mass-assign tenant input.
        $slot = $data['jenis_output'] ?? $existing?->jenis_output;
        if (array_key_exists('skema_output', $data)) {
            $data['skema_output'] = $this->parseSchema($data['skema_output'], $slot);
        } elseif ($existing?->skema_output !== null) {
            $this->parseSchema(json_encode($existing->skema_output, JSON_THROW_ON_ERROR), $slot);
        }

        return $data;
    }

    /** Terima string JSON dari UI -> array (untuk cast model). Kosong => null. */
    private function parseSchema(?string $raw, string $slot): ?array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        try {
            $decoded = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['skema_output' => 'Skema harus JSON valid.']);
        }
        if (! is_object($decoded) && ! is_array($decoded)) {
            throw ValidationException::withMessages(['skema_output' => 'Skema harus objek atau array JSON, bukan nilai skalar.']);
        }

        $default = json_decode(config("prompts.slots.{$slot}.schema", ''));
        if (is_object($default)) {
            if (! is_object($decoded)) {
                throw ValidationException::withMessages(['skema_output' => 'Akar skema harus objek JSON sesuai slot.']);
            }
            foreach (get_object_vars($default) as $key => $value) {
                if (! property_exists($decoded, $key) || gettype($decoded->{$key}) !== gettype($value)) {
                    throw ValidationException::withMessages(['skema_output' => "Kunci akar {$key} wajib ada dengan tipe sesuai skema default."]);
                }
            }
        } elseif (is_array($default) && ! is_array($decoded)) {
            throw ValidationException::withMessages(['skema_output' => 'Akar skema harus array JSON sesuai slot.']);
        }

        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }
}
