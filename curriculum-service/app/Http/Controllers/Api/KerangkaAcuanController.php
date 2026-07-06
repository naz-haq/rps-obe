<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AppliesSorting;
use App\Http\Controllers\Controller;
use App\Http\Resources\ButirAcuanResource;
use App\Http\Resources\KerangkaAcuanResource;
use App\Models\KerangkaAcuan;
use Illuminate\Http\Request;

class KerangkaAcuanController extends Controller
{
    use AppliesSorting;

    public function index(Request $request)
    {
        $query = KerangkaAcuan::query()->with('badanRujukan')->withCount('butir');

        if ($request->filled('badan_rujukan_id')) {
            $query->where('badan_rujukan_id', $request->integer('badan_rujukan_id'));
        }
        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where('nama', 'like', "%{$q}%");
        }

        $this->applySort($query, $request, ['nama', 'versi', 'tanggal_berlaku', 'butir_count', 'created_at'], 'nama');

        return KerangkaAcuanResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'badan_rujukan_id' => ['required', 'exists:badan_rujukan,id'],
            'dokumen_id'       => ['nullable', 'exists:dokumen_rujukan,id'],
            'nama'             => ['required', 'string', 'max:255'],
            'versi'            => ['nullable', 'string', 'max:255'],
            'tanggal_berlaku'  => ['nullable', 'date'],
        ]);

        $kerangka = KerangkaAcuan::create($data);

        return (new KerangkaAcuanResource($kerangka->load('badanRujukan')->loadCount('butir')))
            ->response()->setStatusCode(201);
    }

    /** Detail kerangka + daftar butir beserta status pemenuhan untuk satu institusi. */
    public function show(Request $request, KerangkaAcuan $kerangkaAcuan)
    {
        $institusiId = $request->integer('institusi_id', 1);

        $kerangkaAcuan->load([
            'badanRujukan',
            'butir' => fn($q) => $q->orderBy('urutan')->orderBy('id')
                ->with(['pemenuhan' => fn($p) => $p->where('institusi_id', $institusiId)]),
        ]);

        $butir = $kerangkaAcuan->butir;
        $total = $butir->count();
        $ringkasan = [
            'total'         => $total,
            'terpenuhi'     => 0,
            'sebagian'      => 0,
            'belum'         => 0,
            'tidak_relevan' => 0,
        ];
        foreach ($butir as $b) {
            $status = $b->pemenuhan->first()?->status ?? 'belum';
            $ringkasan[$status] = ($ringkasan[$status] ?? 0) + 1;
        }
        $relevan = $total - $ringkasan['tidak_relevan'];
        $ringkasan['persen'] = $relevan > 0
            ? round((($ringkasan['terpenuhi'] + 0.5 * $ringkasan['sebagian']) / $relevan) * 100, 1)
            : 0.0;

        return response()->json([
            'data' => [
                'kerangka'  => new KerangkaAcuanResource($kerangkaAcuan->loadCount('butir')),
                'butir'     => ButirAcuanResource::collection($butir),
                'ringkasan' => $ringkasan,
            ],
        ]);
    }

    public function update(Request $request, KerangkaAcuan $kerangkaAcuan)
    {
        $data = $request->validate([
            'badan_rujukan_id' => ['sometimes', 'required', 'exists:badan_rujukan,id'],
            'dokumen_id'       => ['nullable', 'exists:dokumen_rujukan,id'],
            'nama'             => ['sometimes', 'required', 'string', 'max:255'],
            'versi'            => ['nullable', 'string', 'max:255'],
            'tanggal_berlaku'  => ['nullable', 'date'],
        ]);

        $kerangkaAcuan->update($data);

        return new KerangkaAcuanResource($kerangkaAcuan->load('badanRujukan')->loadCount('butir'));
    }

    public function destroy(KerangkaAcuan $kerangkaAcuan)
    {
        $kerangkaAcuan->delete();

        return response()->noContent();
    }
}
