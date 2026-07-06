<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AppliesSorting;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProfilLulusanResource;
use App\Models\Kurikulum;
use App\Models\ProfilLulusan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfilLulusanController extends Controller
{
    use AppliesSorting;

    public function index(Request $request)
    {
        $query = ProfilLulusan::query();

        if ($request->filled('kurikulum_id')) {
            $query->where('kurikulum_id', $request->integer('kurikulum_id'));
        }
        if ($request->filled('institusi_id')) {
            $query->where('institusi_id', $request->integer('institusi_id'));
        }
        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where(fn($w) => $w->where('kode', 'like', "%{$q}%")->orWhere('deskripsi', 'like', "%{$q}%"));
        }

        $this->applySort($query, $request, ['kode', 'created_at'], 'kode');

        return ProfilLulusanResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['institusi_id'] = Kurikulum::findOrFail($data['kurikulum_id'])->institusi_id;

        return new ProfilLulusanResource(ProfilLulusan::create($data));
    }

    public function show(ProfilLulusan $profilLulusan)
    {
        return new ProfilLulusanResource($profilLulusan);
    }

    public function update(Request $request, ProfilLulusan $profilLulusan)
    {
        $profilLulusan->update($this->validated($request, $profilLulusan));

        return new ProfilLulusanResource($profilLulusan->fresh());
    }

    public function destroy(ProfilLulusan $profilLulusan)
    {
        $profilLulusan->delete();

        return response()->json(['message' => 'Profil lulusan dihapus.']);
    }

    private function validated(Request $request, ?ProfilLulusan $pl = null): array
    {
        $kurikulumId = $pl?->kurikulum_id ?? $request->integer('kurikulum_id');

        return $request->validate([
            'kurikulum_id' => [$pl ? 'sometimes' : 'required', 'integer', 'exists:kurikulum,id'],
            'kode'         => [
                $pl ? 'sometimes' : 'required',
                'string',
                'max:255',
                Rule::unique('profil_lulusan', 'kode')
                    ->where(fn($q) => $q->where('kurikulum_id', $kurikulumId))
                    ->ignore($pl?->id),
            ],
            'deskripsi'    => [$pl ? 'sometimes' : 'required', 'string'],
        ], [
            'kode.unique' => 'Kode profil lulusan ini sudah dipakai pada kurikulum yang sama.',
        ]);
    }
}
