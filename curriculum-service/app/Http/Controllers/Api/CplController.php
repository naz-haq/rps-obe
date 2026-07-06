<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AppliesSorting;
use App\Http\Controllers\Controller;
use App\Http\Resources\CplResource;
use App\Models\Cpl;
use App\Models\Kurikulum;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CplController extends Controller
{
    use AppliesSorting;

    public function index(Request $request)
    {
        $query = Cpl::query();

        if ($request->filled('kurikulum_id')) {
            $query->where('kurikulum_id', $request->integer('kurikulum_id'));
        }
        if ($request->filled('institusi_id')) {
            $query->where('institusi_id', $request->integer('institusi_id'));
        }
        if ($request->filled('aspek')) {
            $query->where('aspek', $request->string('aspek'));
        }
        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where(fn($w) => $w->where('kode', 'like', "%{$q}%")->orWhere('deskripsi', 'like', "%{$q}%"));
        }

        $this->applySort($query, $request, ['kode', 'aspek', 'level_kkni', 'created_at'], 'kode');

        return CplResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['institusi_id'] = Kurikulum::findOrFail($data['kurikulum_id'])->institusi_id;

        return new CplResource(Cpl::create($data));
    }

    public function show(Cpl $cpl)
    {
        return new CplResource($cpl);
    }

    public function update(Request $request, Cpl $cpl)
    {
        $cpl->update($this->validated($request, $cpl));

        return new CplResource($cpl->fresh());
    }

    public function destroy(Cpl $cpl)
    {
        $cpl->delete();

        return response()->json(['message' => 'CPL dihapus.']);
    }

    private function validated(Request $request, ?Cpl $cpl = null): array
    {
        $kurikulumId = $cpl?->kurikulum_id ?? $request->integer('kurikulum_id');

        return $request->validate([
            'kurikulum_id' => [$cpl ? 'sometimes' : 'required', 'integer', 'exists:kurikulum,id'],
            'kode'         => [
                $cpl ? 'sometimes' : 'required',
                'string',
                'max:255',
                Rule::unique('cpl', 'kode')
                    ->where(fn($q) => $q->where('kurikulum_id', $kurikulumId))
                    ->ignore($cpl?->id),
            ],
            'deskripsi'    => [$cpl ? 'sometimes' : 'required', 'string'],
            'aspek'        => ['nullable', Rule::in(['sikap', 'pengetahuan', 'keterampilan_umum', 'keterampilan_khusus'])],
            'level_kkni'   => ['nullable', 'string', 'max:50'],
            'sumber'       => ['nullable', 'string', 'max:255'],
        ], [
            'kode.unique' => 'Kode CPL ini sudah dipakai pada kurikulum yang sama.',
        ]);
    }
}
