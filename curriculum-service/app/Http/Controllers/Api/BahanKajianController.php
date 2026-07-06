<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AppliesSorting;
use App\Http\Controllers\Controller;
use App\Http\Resources\BahanKajianResource;
use App\Models\BahanKajian;
use App\Models\Kurikulum;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BahanKajianController extends Controller
{
    use AppliesSorting;

    public function index(Request $request)
    {
        $query = BahanKajian::query();

        if ($request->filled('kurikulum_id')) {
            $query->where('kurikulum_id', $request->integer('kurikulum_id'));
        }
        if ($request->filled('institusi_id')) {
            $query->where('institusi_id', $request->integer('institusi_id'));
        }
        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where(fn($w) => $w->where('nama', 'like', "%{$q}%")->orWhere('deskripsi', 'like', "%{$q}%"));
        }

        $this->applySort($query, $request, ['nama', 'created_at'], 'nama');

        return BahanKajianResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['institusi_id'] = Kurikulum::findOrFail($data['kurikulum_id'])->institusi_id;

        return new BahanKajianResource(BahanKajian::create($data));
    }

    public function show(BahanKajian $bahanKajian)
    {
        return new BahanKajianResource($bahanKajian);
    }

    public function update(Request $request, BahanKajian $bahanKajian)
    {
        $bahanKajian->update($this->validated($request, $bahanKajian));

        return new BahanKajianResource($bahanKajian->fresh());
    }

    public function destroy(BahanKajian $bahanKajian)
    {
        $bahanKajian->delete();

        return response()->json(['message' => 'Bahan kajian dihapus.']);
    }

    private function validated(Request $request, ?BahanKajian $bahanKajian = null): array
    {
        $kurikulumId = $bahanKajian?->kurikulum_id ?? $request->integer('kurikulum_id');

        return $request->validate([
            'kurikulum_id' => [$bahanKajian ? 'sometimes' : 'required', 'integer', 'exists:kurikulum,id'],
            'nama'         => [
                $bahanKajian ? 'sometimes' : 'required',
                'string',
                'max:255',
                Rule::unique('bahan_kajian', 'nama')
                    ->where(fn($q) => $q->where('kurikulum_id', $kurikulumId))
                    ->ignore($bahanKajian?->id),
            ],
            'deskripsi'    => ['nullable', 'string'],
        ], [
            'nama.unique' => 'Nama bahan kajian ini sudah ada pada kurikulum yang sama.',
        ]);
    }
}
