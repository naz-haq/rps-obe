<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AppliesSorting;
use App\Http\Controllers\Controller;
use App\Http\Resources\TargetCplResource;
use App\Models\TargetCpl;
use Illuminate\Http\Request;

/**
 * Modul 6 — Kalibrasi TARGET_CPL (ambang nilai + % mahasiswa target per angkatan).
 * Otoritatif & diisi manual; jadi acuan penilaian ketercapaian OBAEI.
 */
class TargetCplController extends Controller
{
    use AppliesSorting;

    public function index(Request $request)
    {
        $query = TargetCpl::query()->with('cpl:id,kode,deskripsi');

        if ($request->filled('institusi_id')) {
            $query->where('institusi_id', $request->integer('institusi_id'));
        }
        if ($request->filled('cpl_id')) {
            $query->where('cpl_id', $request->integer('cpl_id'));
        }
        if ($request->filled('angkatan')) {
            $query->where('angkatan', $request->string('angkatan'));
        }

        $this->applySort($query, $request, ['angkatan', 'ambang_nilai', 'persentase_target', 'created_at'], 'created_at', 'desc');

        return TargetCplResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        // Idempoten per (institusi, cpl, angkatan).
        $target = TargetCpl::updateOrCreate(
            [
                'institusi_id' => $data['institusi_id'],
                'cpl_id'       => $data['cpl_id'],
                'angkatan'     => $data['angkatan'] ?? null,
            ],
            [
                'ambang_nilai'      => $data['ambang_nilai'] ?? null,
                'persentase_target' => $data['persentase_target'] ?? null,
            ]
        );

        return (new TargetCplResource($target->load('cpl:id,kode,deskripsi')))
            ->response()
            ->setStatusCode($target->wasRecentlyCreated ? 201 : 200);
    }

    public function update(Request $request, TargetCpl $targetCpl)
    {
        $data = $this->validated($request, $targetCpl);
        $targetCpl->update($data);

        return new TargetCplResource($targetCpl->load('cpl:id,kode,deskripsi'));
    }

    public function destroy(TargetCpl $targetCpl)
    {
        $targetCpl->delete();

        return response()->json(['message' => 'Target CPL dihapus.']);
    }

    private function validated(Request $request, ?TargetCpl $existing = null): array
    {
        return $request->validate([
            'institusi_id'      => [$existing ? 'sometimes' : 'required', 'integer', 'exists:institusi,id'],
            'cpl_id'            => [$existing ? 'sometimes' : 'required', 'integer', 'exists:cpl,id'],
            'angkatan'          => ['nullable', 'string', 'max:20'],
            'ambang_nilai'      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'persentase_target' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
    }
}
