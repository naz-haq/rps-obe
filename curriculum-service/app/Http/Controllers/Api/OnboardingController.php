<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ColumnMapping;
use App\Services\Onboarding\OnboardingImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Modul 0 — Onboarding & Column-Mapping.
 * preview: parse CSV/rows + sarankan pemetaan kolom.
 * mapping (GET/POST): baca/simpan COLUMN_MAPPING per (institusi, jenis).
 * import: terapkan pemetaan -> upsert entitas kurikulum.
 */
class OnboardingController extends Controller
{
    public function __construct(private OnboardingImportService $service) {}

    /** Pratinjau: kembalikan header, contoh baris, dan saran pemetaan. */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'jenis'       => ['required', Rule::in(OnboardingImportService::JENIS)],
            'konten_csv'  => ['required_without:rows', 'nullable', 'string'],
            'rows'        => ['required_without:konten_csv', 'nullable', 'array'],
        ]);

        $parsed = isset($data['rows'])
            ? $this->service->normalizeRows($data['rows'])
            : $this->service->parseCsv((string) $data['konten_csv']);

        $saran = $this->service->suggestMapping($data['jenis'], $parsed['headers']);

        return response()->json([
            'data' => [
                'jenis'          => $data['jenis'],
                'headers'        => $parsed['headers'],
                'jumlah_baris'   => count($parsed['rows']),
                'contoh_baris'   => array_slice($parsed['rows'], 0, 5),
                'saran_mapping'  => $saran,
            ],
        ]);
    }

    /** Daftar pemetaan kolom tersimpan (filter institusi_id/jenis). */
    public function mappingIndex(Request $request): JsonResponse
    {
        $query = ColumnMapping::query();

        if ($request->filled('institusi_id')) {
            $query->where('institusi_id', $request->integer('institusi_id'));
        }
        if ($request->filled('jenis')) {
            $query->where('jenis_file', $request->string('jenis'));
        }

        return response()->json(['data' => $query->orderBy('jenis_file')->get()]);
    }

    /** Simpan/perbarui pemetaan kolom (updateOrCreate by institusi_id+jenis_file). */
    public function mappingStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'institusi_id' => ['required', 'integer', 'exists:institusi,id'],
            'jenis'        => ['required', Rule::in(OnboardingImportService::JENIS)],
            'mapping'      => ['required', 'array', 'min:1'],
        ]);

        $record = ColumnMapping::updateOrCreate(
            ['institusi_id' => $data['institusi_id'], 'jenis_file' => $data['jenis']],
            ['mapping' => $data['mapping']],
        );

        return response()->json(['data' => $record], $record->wasRecentlyCreated ? 201 : 200);
    }

    /** Impor rows -> entitas kurikulum sesuai pemetaan. */
    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'institusi_id' => ['required', 'integer', 'exists:institusi,id'],
            'kurikulum_id' => ['required', 'integer', 'exists:kurikulum,id'],
            'jenis'        => ['required', Rule::in(OnboardingImportService::JENIS)],
            'mapping'      => ['nullable', 'array'],
            'konten_csv'   => ['required_without:rows', 'nullable', 'string'],
            'rows'         => ['required_without:konten_csv', 'nullable', 'array'],
        ]);

        if (! $this->service->kurikulumMilik($data['institusi_id'], $data['kurikulum_id'])) {
            return response()->json(['message' => 'Kurikulum bukan milik institusi tersebut.'], 422);
        }

        $parsed = isset($data['rows'])
            ? $this->service->normalizeRows($data['rows'])
            : $this->service->parseCsv((string) $data['konten_csv']);

        // Pemetaan: pakai input, atau ambil tersimpan, atau saran otomatis.
        $mapping = $data['mapping'] ?? null;
        if (! $mapping) {
            $tersimpan = ColumnMapping::where('institusi_id', $data['institusi_id'])
                ->where('jenis_file', $data['jenis'])->first();
            $mapping = $tersimpan?->mapping ?? $this->service->suggestMapping($data['jenis'], $parsed['headers']);
        }

        if ($mapping === []) {
            return response()->json(['message' => 'Pemetaan kolom kosong; tidak ada yang bisa diimpor.'], 422);
        }

        $ringkasan = $this->service->import(
            $data['institusi_id'],
            $data['kurikulum_id'],
            $data['jenis'],
            $parsed['rows'],
            $mapping,
        );

        return response()->json([
            'message' => 'Impor selesai.',
            'data'    => array_merge(['mapping' => $mapping], $ringkasan),
        ]);
    }
}
