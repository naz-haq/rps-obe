<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AppliesSorting;
use App\Http\Controllers\Controller;
use App\Http\Resources\BadanRujukanResource;
use App\Models\BadanRujukan;
use Illuminate\Http\Request;

class BadanRujukanController extends Controller
{
    use AppliesSorting;

    public function index(Request $request)
    {
        $query = BadanRujukan::query()->withCount('dokumen');

        if ($request->filled('institusi_id')) {
            $iid = $request->integer('institusi_id');
            $query->where(fn($q) => $q->where('institusi_id', $iid)->orWhereNull('institusi_id'));
        }
        if ($request->filled('jenis')) {
            $query->where('jenis', $request->string('jenis'));
        }
        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where('nama', 'like', "%{$q}%");
        }

        $this->applySort($query, $request, ['nama', 'jenis', 'dokumen_count', 'created_at'], 'nama');

        return BadanRujukanResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'institusi_id' => ['nullable', 'exists:institusi,id'],
            'nama'         => ['required', 'string', 'max:255'],
            'jenis'        => ['required', 'in:asosiasi,akreditasi,pemerintah,institusi'],
            'disiplin'     => ['nullable', 'string', 'max:255'],
        ]);

        $badan = BadanRujukan::create($data);

        return new BadanRujukanResource($badan->loadCount('dokumen'));
    }

    public function show(BadanRujukan $badanRujukan)
    {
        return new BadanRujukanResource($badanRujukan->loadCount('dokumen'));
    }

    public function update(Request $request, BadanRujukan $badanRujukan)
    {
        $data = $request->validate([
            'institusi_id' => ['nullable', 'exists:institusi,id'],
            'nama'         => ['sometimes', 'required', 'string', 'max:255'],
            'jenis'        => ['sometimes', 'required', 'in:asosiasi,akreditasi,pemerintah,institusi'],
            'disiplin'     => ['nullable', 'string', 'max:255'],
        ]);

        $badanRujukan->update($data);

        return new BadanRujukanResource($badanRujukan->loadCount('dokumen'));
    }

    public function destroy(BadanRujukan $badanRujukan)
    {
        $badanRujukan->delete();

        return response()->noContent();
    }
}
