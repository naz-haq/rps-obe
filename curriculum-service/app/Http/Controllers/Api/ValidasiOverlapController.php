<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AppliesSorting;
use App\Http\Controllers\Controller;
use App\Http\Resources\ValidasiOverlapResource;
use App\Models\ValidasiOverlap;
use App\Services\Ai\AiService;
use App\Services\Compliance\OverlapValidatorService;
use Illuminate\Http\Request;

/**
 * Modul 3 — Validator Overlap (Blueprint 6, Fase 2).
 * Mengekspos: index (daftar temuan), pindai (deteksi), analisis (AI), review.
 */
class ValidasiOverlapController extends Controller
{
    use AppliesSorting;

    public function __construct(private OverlapValidatorService $validator) {}

    public function index(Request $request)
    {
        $query = ValidasiOverlap::query()
            ->with(['keterampilan.bahanKajian']);

        if ($request->filled('institusi_id')) {
            $query->where('institusi_id', $request->integer('institusi_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('kurikulum_id')) {
            $kurikulumId = $request->integer('kurikulum_id');
            $query->whereHas('keterampilan.bahanKajian', fn($q) => $q->where('kurikulum_id', $kurikulumId));
        }

        $this->applySort($query, $request, ['status', 'created_at', 'updated_at'], 'created_at', 'desc');

        return ValidasiOverlapResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    public function show(ValidasiOverlap $validasiOverlap)
    {
        return new ValidasiOverlapResource($validasiOverlap->load('keterampilan.bahanKajian'));
    }

    /** Jalankan deteksi overlap deterministik untuk satu institusi/kurikulum. */
    public function pindai(Request $request)
    {
        $data = $request->validate([
            'institusi_id' => ['required', 'integer'],
            'kurikulum_id' => ['nullable', 'integer'],
        ]);

        $ringkasan = $this->validator->pindai($data['institusi_id'], $data['kurikulum_id'] ?? null);

        return response()->json(['data' => $ringkasan]);
    }

    /** Lengkapi analisis + rekomendasi satu temuan dengan AI. */
    public function analisis(ValidasiOverlap $validasiOverlap, AiService $ai)
    {
        $overlap = $this->validator->analisisAi($validasiOverlap, $ai);

        return new ValidasiOverlapResource($overlap->load('keterampilan.bahanKajian'));
    }

    /** Tinjauan manusia: set status akhir + catatan rekomendasi. */
    public function review(Request $request, ValidasiOverlap $validasiOverlap)
    {
        $data = $request->validate([
            'status'      => ['required', 'in:overlap,aman,perlu_review'],
            'rekomendasi' => ['nullable', 'string'],
        ]);

        $validasiOverlap->update([
            'status'      => $data['status'],
            'rekomendasi' => $data['rekomendasi'] ?? $validasiOverlap->rekomendasi,
            'reviewed_by' => $request->user()?->id,
        ]);

        return new ValidasiOverlapResource($validasiOverlap->load('keterampilan.bahanKajian'));
    }
}
