<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AppliesSorting;
use App\Http\Controllers\Controller;
use App\Http\Resources\MataKuliahResource;
use App\Models\MataKuliah;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MataKuliahController extends Controller
{
    use AppliesSorting;

    public function index(Request $request)
    {
        $query = MataKuliah::query()->with('institusi:id,nama');

        if ($request->filled('kurikulum_id')) {
            $query->where('kurikulum_id', $request->integer('kurikulum_id'));
        }
        if ($request->filled('institusi_id')) {
            $query->where('institusi_id', $request->integer('institusi_id'));
        }
        if ($request->filled('jenis_mk')) {
            $query->where('jenis_mk', $request->string('jenis_mk'));
        }
        if ($request->filled('semester')) {
            $query->where('semester', $request->integer('semester'));
        }
        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where(fn($w) => $w->where('kode_mk', 'like', "%{$q}%")->orWhere('nama', 'like', "%{$q}%"));
        }

        $this->applySort($query, $request, ['kode_mk', 'nama', 'jenis_mk', 'semester', 'created_at'], 'kode_mk');

        return MataKuliahResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request)
    {
        return new MataKuliahResource(MataKuliah::create($this->validated($request)));
    }

    public function show(MataKuliah $mataKuliah)
    {
        return new MataKuliahResource($mataKuliah);
    }

    public function update(Request $request, MataKuliah $mataKuliah)
    {
        $mataKuliah->update($this->validated($request, $mataKuliah));

        return new MataKuliahResource($mataKuliah->fresh());
    }

    public function destroy(MataKuliah $mataKuliah)
    {
        $mataKuliah->delete();

        return response()->json(['message' => 'Mata kuliah dihapus.']);
    }

    private function validated(Request $request, ?MataKuliah $mk = null): array
    {
        $institusiId = $mk?->institusi_id ?? $request->integer('institusi_id');

        return $request->validate([
            'institusi_id'      => [$mk ? 'sometimes' : 'required', 'integer', 'exists:institusi,id'],
            'kurikulum_id'      => ['nullable', 'integer', 'exists:kurikulum,id'],
            'kode_mk'           => [
                $mk ? 'sometimes' : 'required',
                'string',
                'max:255',
                Rule::unique('mata_kuliah', 'kode_mk')
                    ->where(fn($q) => $q->where('institusi_id', $institusiId))
                    ->ignore($mk?->id),
            ],
            'nama'              => [$mk ? 'sometimes' : 'required', 'string', 'max:255'],
            'jenis_mk'          => ['nullable', Rule::in(['murni', 'praktikum'])],
            'sifat'             => ['nullable', Rule::in(['wajib', 'pilihan'])],
            'rumpun'            => ['nullable', 'string', 'max:255'],
            'deskripsi_singkat' => ['nullable', 'string'],
            'sks_teori'         => ['nullable', 'integer', 'min:0', 'max:12'],
            'sks_praktik'       => ['nullable', 'integer', 'min:0', 'max:12'],
            'semester'          => ['nullable', 'integer', 'min:1', 'max:14'],
            'prodi_kode'        => ['nullable', 'string', 'max:255'],
            'prasyarat_kode'    => ['nullable', 'string', 'max:255'],
        ], [
            'kode_mk.unique' => 'Kode mata kuliah ini sudah dipakai pada institusi yang sama.',
        ]);
    }
}
