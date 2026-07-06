<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AppliesSorting;
use App\Http\Controllers\Controller;
use App\Http\Resources\CapaianMahasiswaResource;
use App\Models\CapaianMahasiswa;
use Illuminate\Http\Request;

/**
 * Modul 6 — Input capaian mahasiswa AGREGAT per MK/CPMK/Sub-CPMK.
 * (Nantinya bisa diisi otomatis dari LMS Service; kini manual/impor.)
 */
class CapaianMahasiswaController extends Controller
{
    use AppliesSorting;

    public function index(Request $request)
    {
        $query = CapaianMahasiswa::query()->with(['subCpmk:id,kode', 'cpmk:id,kode']);

        if ($request->filled('institusi_id')) {
            $query->where('institusi_id', $request->integer('institusi_id'));
        }
        if ($request->filled('kode_mk')) {
            $query->where('kode_mk', $request->string('kode_mk'));
        }
        if ($request->filled('angkatan')) {
            $query->where('angkatan', $request->string('angkatan'));
        }

        $this->applySort(
            $query,
            $request,
            ['kode_mk', 'angkatan', 'nilai_rata_rata', 'persentase_capaian_minimal', 'created_at'],
            'created_at',
            'desc'
        );

        return CapaianMahasiswaResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request)
    {
        $capaian = CapaianMahasiswa::create($this->validated($request));

        return (new CapaianMahasiswaResource($capaian->load(['subCpmk:id,kode', 'cpmk:id,kode'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, CapaianMahasiswa $capaianMahasiswa)
    {
        $capaianMahasiswa->update($this->validated($request, $capaianMahasiswa));

        return new CapaianMahasiswaResource($capaianMahasiswa->load(['subCpmk:id,kode', 'cpmk:id,kode']));
    }

    public function destroy(CapaianMahasiswa $capaianMahasiswa)
    {
        $capaianMahasiswa->delete();

        return response()->json(['message' => 'Capaian mahasiswa dihapus.']);
    }

    private function validated(Request $request, ?CapaianMahasiswa $existing = null): array
    {
        return $request->validate([
            'institusi_id'                => [$existing ? 'sometimes' : 'required', 'integer', 'exists:institusi,id'],
            'kode_mk'                     => [$existing ? 'sometimes' : 'required', 'string', 'max:50'],
            'sub_cpmk_id'                 => ['nullable', 'integer', 'exists:sub_cpmk,id'],
            'cpmk_id'                     => ['nullable', 'integer', 'exists:cpmk,id'],
            'angkatan'                    => ['nullable', 'string', 'max:20'],
            'jumlah_mahasiswa'            => ['nullable', 'integer', 'min:0'],
            'nilai_rata_rata'             => ['nullable', 'numeric', 'min:0', 'max:100'],
            'persentase_capaian_minimal'  => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
    }
}
