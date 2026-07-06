<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TindakLanjutResource;
use App\Models\EvaluasiCpl;
use App\Models\TindakLanjut;
use Illuminate\Http\Request;

/**
 * Modul 6 — Tindak lanjut hasil evaluasi CPL. Mengalir ke siklus generate RPS
 * berikutnya (VERSI_PEDOMAN.mk_terdampak / catatan perbaikan).
 */
class TindakLanjutController extends Controller
{
    public function store(Request $request, EvaluasiCpl $evaluasiCpl)
    {
        $data = $request->validate([
            'sub_cpmk_id' => ['nullable', 'integer', 'exists:sub_cpmk,id'],
            'catatan'     => ['required', 'string'],
            'prioritas'   => ['nullable', 'in:tinggi,sedang,rendah'],
            'status'      => ['nullable', 'string', 'max:30'],
        ]);
        $data['institusi_id'] = $evaluasiCpl->institusi_id;
        $data['status'] = $data['status'] ?? 'usulan';

        $tindak = $evaluasiCpl->tindakLanjut()->create($data);

        return (new TindakLanjutResource($tindak->load('subCpmk:id,kode')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, TindakLanjut $tindakLanjut)
    {
        $data = $request->validate([
            'sub_cpmk_id' => ['nullable', 'integer', 'exists:sub_cpmk,id'],
            'catatan'     => ['sometimes', 'string'],
            'prioritas'   => ['nullable', 'in:tinggi,sedang,rendah'],
            'status'      => ['nullable', 'string', 'max:30'],
        ]);

        $tindakLanjut->update($data);

        return new TindakLanjutResource($tindakLanjut->load('subCpmk:id,kode'));
    }

    public function destroy(TindakLanjut $tindakLanjut)
    {
        $tindakLanjut->delete();

        return response()->json(['message' => 'Tindak lanjut dihapus.']);
    }
}
