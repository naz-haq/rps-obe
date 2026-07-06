<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AppliesSorting;
use App\Http\Controllers\Controller;
use App\Http\Resources\EvaluasiCplResource;
use App\Models\EvaluasiCpl;
use App\Services\Ai\AiService;
use App\Services\Governance\AuditLogger;
use App\Services\Obaei\EvaluasiCplService;
use Illuminate\Http\Request;

/**
 * Modul 6 — Evaluasi ketercapaian CPL & agregasi OBAEI (closing the loop).
 */
class EvaluasiCplController extends Controller
{
    use AppliesSorting;

    public function __construct(private EvaluasiCplService $service) {}

    /** Dashboard agregasi: status ketercapaian tiap CPL vs target. */
    public function agregasi(Request $request)
    {
        $request->validate([
            'institusi_id' => ['required', 'integer', 'exists:institusi,id'],
            'angkatan'     => ['nullable', 'string', 'max:20'],
            'kurikulum_id' => ['nullable', 'integer', 'exists:kurikulum,id'],
        ]);

        $data = $this->service->agregasi(
            $request->integer('institusi_id'),
            $request->filled('angkatan') ? (string) $request->string('angkatan') : null,
            $request->filled('kurikulum_id') ? $request->integer('kurikulum_id') : null,
        );

        return response()->json([
            'data' => [
                'ringkasan' => $this->service->ringkasan($data),
                'cpl'       => $data,
            ],
        ]);
    }

    public function index(Request $request)
    {
        $query = EvaluasiCpl::query()
            ->with('cpl:id,kode,deskripsi')
            ->withCount('tindakLanjut');

        if ($request->filled('institusi_id')) {
            $query->where('institusi_id', $request->integer('institusi_id'));
        }
        if ($request->filled('cpl_id')) {
            $query->where('cpl_id', $request->integer('cpl_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('periode')) {
            $query->where('periode', $request->string('periode'));
        }

        $this->applySort($query, $request, ['periode', 'status', 'created_at', 'updated_at'], 'created_at', 'desc');

        return EvaluasiCplResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    public function show(EvaluasiCpl $evaluasiCpl)
    {
        $evaluasiCpl->load(['cpl:id,kode,deskripsi', 'tindakLanjut.subCpmk:id,kode']);

        return new EvaluasiCplResource($evaluasiCpl);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'institusi_id'      => ['required', 'integer', 'exists:institusi,id'],
            'cpl_id'            => ['required', 'integer', 'exists:cpl,id'],
            'periode'           => ['nullable', 'string', 'max:50'],
            'ringkasan_naratif' => ['nullable', 'string'],
        ]);
        $data['status'] = 'draft';
        $data['dibuat_oleh'] = $request->user()?->id;

        $evaluasi = EvaluasiCpl::create($data);

        return (new EvaluasiCplResource($evaluasi->load('cpl:id,kode,deskripsi')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, EvaluasiCpl $evaluasiCpl)
    {
        $data = $request->validate([
            'periode'           => ['nullable', 'string', 'max:50'],
            'ringkasan_naratif' => ['nullable', 'string'],
        ]);

        $evaluasiCpl->update($data);

        return new EvaluasiCplResource($evaluasiCpl->load('cpl:id,kode,deskripsi'));
    }

    /** Lengkapi ringkasan naratif + usulan tindak lanjut dengan AI (tidak menimpa bila kosong). */
    public function analisis(Request $request, EvaluasiCpl $evaluasiCpl, AiService $ai)
    {
        $hasil = $this->service->analisisAi(
            $evaluasiCpl,
            $ai,
            $request->filled('angkatan') ? (string) $request->string('angkatan') : $evaluasiCpl->periode,
        );

        if ($hasil['ringkasan']) {
            $evaluasiCpl->ringkasan_naratif = $hasil['ringkasan'];
            $evaluasiCpl->save();
        }

        foreach ($hasil['tindak_lanjut'] as $item) {
            $catatan = is_string($item['catatan'] ?? null) ? trim($item['catatan']) : '';
            if ($catatan === '') {
                continue;
            }
            $evaluasiCpl->tindakLanjut()->create([
                'institusi_id' => $evaluasiCpl->institusi_id,
                'catatan'      => $catatan,
                'prioritas'    => in_array($item['prioritas'] ?? null, ['tinggi', 'sedang', 'rendah'], true)
                    ? $item['prioritas']
                    : null,
                'status'       => 'usulan',
            ]);
        }

        return new EvaluasiCplResource(
            $evaluasiCpl->load(['cpl:id,kode,deskripsi', 'tindakLanjut.subCpmk:id,kode'])
        );
    }

    /** Finalisasi evaluasi (kunci sebagai bukti akreditasi). */
    public function finalisasi(Request $request, EvaluasiCpl $evaluasiCpl)
    {
        $evaluasiCpl->update([
            'status'           => 'final',
            'difinalisasi_oleh' => $request->user()?->id,
        ]);

        $aktor = AuditLogger::aktorDariRequest($request);
        AuditLogger::catat(
            action: 'obaei.finalisasi',
            entity: 'EvaluasiCpl',
            entityId: $evaluasiCpl->id,
            meta: ['cpl_id' => $evaluasiCpl->cpl_id, 'periode' => $evaluasiCpl->periode],
            institusiId: $evaluasiCpl->institusi_id,
            userId: $aktor['user_id'],
            actorNama: $aktor['actor_nama'],
        );

        return new EvaluasiCplResource($evaluasiCpl->load('cpl:id,kode,deskripsi'));
    }

    public function destroy(EvaluasiCpl $evaluasiCpl)
    {
        $evaluasiCpl->delete();

        return response()->json(['message' => 'Evaluasi CPL dihapus.']);
    }
}
