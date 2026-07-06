<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AppliesSorting;
use App\Http\Controllers\Controller;
use App\Http\Resources\KurikulumResource;
use App\Models\Kurikulum;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KurikulumController extends Controller
{
    use AppliesSorting;

    public function index(Request $request)
    {
        $query = Kurikulum::query()->withCount(['mataKuliah', 'cpl']);

        if ($request->filled('institusi_id')) {
            $query->where('institusi_id', $request->integer('institusi_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where(fn($w) => $w->where('nama', 'like', "%{$q}%")->orWhere('kode', 'like', "%{$q}%"));
        }

        $this->applySort($query, $request, ['nama', 'tahun', 'status', 'created_at'], 'tahun', 'desc');

        return KurikulumResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        return new KurikulumResource(Kurikulum::create($data));
    }

    public function show(Kurikulum $kurikulum)
    {
        $kurikulum->loadCount(['mataKuliah', 'cpl']);

        return new KurikulumResource($kurikulum);
    }

    public function update(Request $request, Kurikulum $kurikulum)
    {
        $kurikulum->update($this->validated($request, $kurikulum));

        return new KurikulumResource($kurikulum->fresh());
    }

    public function destroy(Kurikulum $kurikulum)
    {
        $kurikulum->delete();

        return response()->json(['message' => 'Kurikulum dihapus.']);
    }

    private function validated(Request $request, ?Kurikulum $kurikulum = null): array
    {
        return $request->validate([
            'institusi_id'    => [$kurikulum ? 'sometimes' : 'required', 'integer', 'exists:institusi,id'],
            'kode'            => ['nullable', 'string', 'max:255'],
            'nama'            => [$kurikulum ? 'sometimes' : 'required', 'string', 'max:255'],
            'tahun'           => [$kurikulum ? 'sometimes' : 'required', 'string', 'max:20'],
            'status'          => ['nullable', Rule::in(['draft', 'berlaku', 'arsip'])],
            'tanggal_berlaku' => ['nullable', 'date'],
            'tanggal_pensiun' => ['nullable', 'date'],
            'mengganti_id'    => ['nullable', 'integer', 'exists:kurikulum,id'],
        ]);
    }
}
