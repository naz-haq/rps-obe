<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AppliesSorting;
use App\Http\Controllers\Controller;
use App\Http\Resources\GenerateSessionResource;
use App\Http\Resources\RpsVersionResource;
use App\Models\GenerateSession;
use App\Models\MataKuliah;
use App\Services\Generator\Exceptions\GeneratorException;
use App\Services\Generator\Exceptions\RevisiConflictException;
use App\Services\Generator\RpsGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Modul 2 — RPS Generator (bertahap, Blueprint 7.4/7.5).
 * Mengekspos RpsGeneratorService: start -> generate -> accept/reject/pin -> commit.
 * Aturan urutan/kunci & grounding ditegakkan di service; exception dipetakan 422.
 */
class GenerateSessionController extends Controller
{
    use AppliesSorting;

    public function __construct(private RpsGeneratorService $generator) {}

    public function index(Request $request)
    {
        $query = GenerateSession::query()->with('mataKuliah');

        if ($request->filled('institusi_id')) {
            $query->where('institusi_id', $request->integer('institusi_id'));
        }
        if ($request->filled('mk_id')) {
            $query->where('mk_id', $request->integer('mk_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $this->applySort($query, $request, ['tahap', 'status', 'created_at', 'updated_at'], 'created_at', 'desc');

        return GenerateSessionResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    public function show(GenerateSession $generateSession)
    {
        $this->generator->ensureItemIds($generateSession);
        $this->generator->ensureEvaluasiMingguan($generateSession);

        return new GenerateSessionResource($generateSession->load('mataKuliah'));
    }

    /**
     * Hapus sesi penyusunan (draf staging). RPS yang SUDAH dikomit berada di
     * tabel terpisah (rps_version) dan TIDAK ikut terhapus.
     */
    public function destroy(GenerateSession $generateSession)
    {
        $generateSession->delete();

        return response()->json(['message' => 'Sesi penyusunan dihapus.']);
    }

    /** Mulai sesi penyusunan untuk satu MK. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'mk_id'   => ['required', 'integer', 'exists:mata_kuliah,id'],
            'sumber'  => ['nullable', Rule::in(['baru', 'impor_rps_lama', 'copy_tahun_lalu'])],
            'user_id' => ['nullable', 'integer'],
            'kompetensi_khusus'   => ['nullable', 'string', 'max:8000'],
            'bok'                 => ['nullable', 'string', 'max:8000'],
            'bahan_kajian_khusus' => ['nullable', 'string', 'max:8000'],
        ]);

        $konteks = array_filter([
            'kompetensi_khusus'   => trim((string) ($data['kompetensi_khusus'] ?? '')),
            'bok'                 => trim((string) ($data['bok'] ?? '')),
            'bahan_kajian_khusus' => trim((string) ($data['bahan_kajian_khusus'] ?? '')),
        ], fn($v) => $v !== '');

        $mk = MataKuliah::findOrFail($data['mk_id']);
        $session = $this->generator->start($mk, [
            'sumber'  => $data['sumber'] ?? 'baru',
            'user_id' => $data['user_id'] ?? null,
            'konteks_tambahan' => $konteks !== [] ? $konteks : null,
        ]);

        return (new GenerateSessionResource($session->load('mataKuliah')))
            ->response()->setStatusCode(201);
    }

    /** Ubah rujukan tambahan dosen (kompetensi khusus/BoK/bahan kajian khusus) sebelum generate. */
    public function konteks(Request $request, GenerateSession $generateSession)
    {
        $data = $request->validate([
            'kompetensi_khusus'   => ['nullable', 'string', 'max:8000'],
            'bok'                 => ['nullable', 'string', 'max:8000'],
            'bahan_kajian_khusus' => ['nullable', 'string', 'max:8000'],
        ]);

        $konteks = array_filter([
            'kompetensi_khusus'   => trim((string) ($data['kompetensi_khusus'] ?? '')),
            'bok'                 => trim((string) ($data['bok'] ?? '')),
            'bahan_kajian_khusus' => trim((string) ($data['bahan_kajian_khusus'] ?? '')),
        ], fn($v) => $v !== '');

        $generateSession->update(['konteks_tambahan' => $konteks !== [] ? $konteks : null]);

        return new GenerateSessionResource($generateSession->load('mataKuliah'));
    }

    /** Generate satu tahap (satu panggilan AI + grounding + auto-regen). */
    public function generate(Request $request, GenerateSession $generateSession)
    {
        $stage = $this->stage($request);

        return $this->run(fn() => $this->generator->generateStage($generateSession, $stage), $generateSession);
    }

    /** Setujui tahap (opsional dengan hasil suntingan manusia). */
    public function accept(Request $request, GenerateSession $generateSession)
    {
        $data = $request->validate([
            'stage'  => ['required', 'string'],
            'edited' => ['nullable', 'array'],
        ]);

        return $this->run(
            fn() => $this->generator->acceptStage($generateSession, $data['stage'], $data['edited'] ?? null),
            $generateSession,
        );
    }

    public function reject(Request $request, GenerateSession $generateSession)
    {
        $stage = $this->stage($request);

        return $this->run(fn() => $this->generator->rejectStage($generateSession, $stage), $generateSession);
    }

    public function pin(Request $request, GenerateSession $generateSession)
    {
        $stage = $this->stage($request);

        return $this->run(fn() => $this->generator->pinStage($generateSession, $stage), $generateSession);
    }

    /** Commit draf ke entitas RPS resmi (menuntut semua tahap terkunci). */
    public function commit(GenerateSession $generateSession)
    {
        try {
            $rps = $this->generator->commit($generateSession);
        } catch (GeneratorException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $rps->loadCount(['minggu', 'komponenPenilaian']);

        return response()->json([
            'message' => 'RPS berhasil dikomit.',
            'data'    => [
                'session' => new GenerateSessionResource($generateSession->fresh()->load('mataKuliah')),
                'rps'     => new RpsVersionResource($rps),
            ],
        ], 201);
    }

    /** Usulan perbaikan SATU item (candidate patch) — tanpa menyentuh draf. */
    public function itemCandidate(Request $request, GenerateSession $generateSession)
    {
        $data = $request->validate([
            'stage'       => ['required', 'string', Rule::in(config('generator.pipeline'))],
            'item_id'     => ['required', 'string'],
            'action'      => ['nullable', 'string', Rule::in(['perbaiki_redaksi', 'buat_alternatif', 'naikkan_taksonomi', 'periksa_konsistensi', 'perkaya'])],
            'instruction' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $candidate = $this->generator->regenerateItem($generateSession, $data['stage'], $data['item_id'], [
                'action'      => $data['action'] ?? null,
                'instruction' => $data['instruction'] ?? null,
            ]);
        } catch (GeneratorException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $candidate]);
    }

    /** Terapkan usulan satu item (optimistic locking; konflik revisi -> 409). */
    public function itemApply(Request $request, GenerateSession $generateSession)
    {
        $data = $request->validate([
            'stage'       => ['required', 'string', Rule::in(config('generator.pipeline'))],
            'item_id'     => ['required', 'string'],
            'after'       => ['required', 'array'],
            'base_revisi' => ['required', 'integer'],
        ]);

        try {
            $this->generator->applyItem($generateSession, $data['stage'], $data['item_id'], $data['after'], (int) $data['base_revisi']);
        } catch (RevisiConflictException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (GeneratorException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new GenerateSessionResource($generateSession->fresh()->load('mataKuliah'));
    }

    /** Sematkan/lepas sematan satu item. */
    public function itemPin(Request $request, GenerateSession $generateSession)
    {
        $data = $request->validate([
            'stage'   => ['required', 'string', Rule::in(config('generator.pipeline'))],
            'item_id' => ['required', 'string'],
            'pinned'  => ['required', 'boolean'],
        ]);

        try {
            $this->generator->setItemPin($generateSession, $data['stage'], $data['item_id'], (bool) $data['pinned']);
        } catch (GeneratorException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new GenerateSessionResource($generateSession->fresh()->load('mataKuliah'));
    }

    /** Jalankan aksi generator, petakan GeneratorException -> 422. */
    private function run(callable $action, GenerateSession $session)
    {
        try {
            $action();
        } catch (GeneratorException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new GenerateSessionResource($session->fresh()->load('mataKuliah'));
    }

    private function stage(Request $request): string
    {
        return $request->validate([
            'stage' => ['required', 'string', Rule::in(config('generator.pipeline'))],
        ])['stage'];
    }
}
