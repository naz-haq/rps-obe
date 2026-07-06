<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AppliesSorting;
use App\Http\Controllers\Controller;
use App\Http\Resources\TaksonomiResource;
use App\Models\Taksonomi;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Taksonomi master (Bloom-Anderson kognitif, Krathwohl afektif, Dave/Simpson
 * psikomotorik) + kata kerja operasional. Data global (institusi_id null) =
 * bawaan; tenant boleh menambah/override entri sendiri.
 */
class TaksonomiController extends Controller
{
    use AppliesSorting;

    public const DOMAIN = ['kognitif', 'afektif', 'psikomotorik'];
    public const KERANGKA = ['bloom_anderson', 'krathwohl', 'dave', 'simpson'];

    public function index(Request $request)
    {
        $query = Taksonomi::query();

        // Global (null) + milik tenant bila institusi_id dikirim.
        if ($request->filled('institusi_id')) {
            $id = $request->integer('institusi_id');
            $query->where(fn($w) => $w->whereNull('institusi_id')->orWhere('institusi_id', $id));
        }
        if ($request->filled('domain')) {
            $query->where('domain', $request->string('domain'));
        }
        if ($request->filled('kerangka')) {
            $query->where('kerangka', $request->string('kerangka'));
        }
        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where(fn($w) => $w->where('nama', 'like', "%{$q}%")->orWhere('kode', 'like', "%{$q}%"));
        }

        $this->applySort(
            $query,
            $request,
            ['domain', 'kerangka', 'kode', 'nama', 'level', 'created_at'],
            'level'
        );

        return TaksonomiResource::collection($query->paginate($request->integer('per_page', 100)));
    }

    public function store(Request $request)
    {
        return new TaksonomiResource(Taksonomi::create($this->validated($request)));
    }

    public function show(Taksonomi $taksonomi)
    {
        return new TaksonomiResource($taksonomi);
    }

    public function update(Request $request, Taksonomi $taksonomi)
    {
        $taksonomi->update($this->validated($request, $taksonomi));

        return new TaksonomiResource($taksonomi->fresh());
    }

    public function destroy(Taksonomi $taksonomi)
    {
        $taksonomi->delete();

        return response()->json(['message' => 'Taksonomi dihapus.']);
    }

    private function validated(Request $request, ?Taksonomi $taksonomi = null): array
    {
        $req = $taksonomi ? 'sometimes' : 'required';

        $data = $request->validate([
            'institusi_id' => ['nullable', 'integer', 'exists:institusi,id'],
            'domain'       => [$req, Rule::in(self::DOMAIN)],
            'kerangka'     => [$req, Rule::in(self::KERANGKA)],
            'kode'         => [$req, 'string', 'max:20'],
            'nama'         => [$req, 'string', 'max:255'],
            'level'        => [$req, 'integer', 'min:1', 'max:10'],
            'deskripsi'    => ['nullable', 'string'],
            'kata_kerja'   => ['nullable', 'array'],
            'kata_kerja.*' => ['string', 'max:60'],
        ]);

        // Normalisasi kata kerja: buang kosong + trim.
        if (array_key_exists('kata_kerja', $data)) {
            $data['kata_kerja'] = array_values(array_filter(
                array_map(fn($v) => trim((string) $v), $data['kata_kerja']),
                fn($v) => $v !== ''
            ));
        }

        return $data;
    }
}
