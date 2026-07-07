<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Institusi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CRUD institusi (universitas/fakultas/prodi) untuk penetapan unit pengguna & data akademik.
 */
class InstitusiController extends Controller
{
    private const JENIS = ['universitas', 'fakultas', 'prodi'];

    public function index(): JsonResponse
    {
        $data = Institusi::query()
            ->with('parent:id,nama')
            ->withCount([
                'mataKuliah',
                'users as dosen_count' => fn($q) => $q->whereHas('roles', fn($r) => $r->where('name', 'dosen')),
            ])
            ->orderByRaw("FIELD(jenis, 'universitas', 'fakultas', 'prodi')")
            ->orderBy('nama')
            ->get(['id', 'kode', 'nama', 'jenis', 'parent_id', 'asosiasi_profesi'])
            ->map(fn(Institusi $i) => [
                'id' => $i->id,
                'kode' => $i->kode,
                'nama' => $i->nama,
                'jenis' => $i->jenis,
                'parent_id' => $i->parent_id,
                'parent_nama' => $i->parent?->nama,
                'asosiasi_profesi' => $i->asosiasi_profesi,
                'dosen_count' => $i->dosen_count,
                'mata_kuliah_count' => $i->mata_kuliah_count,
            ]);

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validasi($request);
        $institusi = Institusi::create($data);

        return response()->json(['data' => $this->format($institusi)], 201);
    }

    public function update(Request $request, Institusi $institusi): JsonResponse
    {
        $data = $this->validasi($request, $institusi->id);
        $institusi->update($data);

        return response()->json(['data' => $this->format($institusi->fresh())]);
    }

    public function destroy(Institusi $institusi): JsonResponse
    {
        if ($institusi->children()->exists()) {
            return response()->json([
                'message' => 'Unit ini masih memiliki unit turunan (fakultas/prodi) dan tidak dapat dihapus.',
            ], 422);
        }

        if ($institusi->users()->exists() || $institusi->mataKuliah()->exists()) {
            return response()->json([
                'message' => 'Prodi/unit ini masih memiliki pengguna terafiliasi atau mata kuliah dan tidak dapat dihapus.',
            ], 422);
        }

        $institusi->delete();

        return response()->json(['message' => 'Prodi/unit dihapus.']);
    }

    private function validasi(Request $request, ?int $ignoreId = null): array
    {
        $jenis = $request->input('jenis');
        // Induk berjenjang: prodi -> fakultas, fakultas -> universitas.
        $indukJenis = match ($jenis) {
            'prodi'    => 'fakultas',
            'fakultas' => 'universitas',
            default    => null,
        };

        $parentRule = array_values(array_filter([
            Rule::requiredIf(fn() => $request->input('jenis') === 'prodi'),
            'nullable',
            'integer',
            $indukJenis ? Rule::exists('institusi', 'id')->where('jenis', $indukJenis) : null,
            Rule::notIn($ignoreId ? [$ignoreId] : []),
        ]));

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'jenis' => ['required', Rule::in(self::JENIS)],
            'parent_id' => $parentRule,
            'kode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('institusi', 'kode')->ignore($ignoreId),
            ],
            'asosiasi_profesi' => ['nullable', 'string', 'max:255'],
        ], [
            'nama.required' => 'Nama prodi/unit wajib diisi.',
            'jenis.in' => 'Jenis harus universitas, fakultas, atau prodi.',
            'parent_id.required' => 'Prodi wajib terikat pada satu fakultas.',
            'parent_id.exists' => 'Induk yang dipilih tidak valid.',
            'parent_id.not_in' => 'Unit tidak boleh menjadi induk dirinya sendiri.',
            'kode.unique' => 'Kode ini sudah dipakai unit lain.',
        ]);

        // Universitas adalah unit puncak: tidak punya induk.
        if (($data['jenis'] ?? null) === 'universitas') {
            $data['parent_id'] = null;
        }

        return $data;
    }

    private function format(Institusi $institusi): array
    {
        return [
            'id' => $institusi->id,
            'kode' => $institusi->kode,
            'nama' => $institusi->nama,
            'jenis' => $institusi->jenis,
            'parent_id' => $institusi->parent_id,
            'parent_nama' => $institusi->parent?->nama,
            'asosiasi_profesi' => $institusi->asosiasi_profesi,
            'dosen_count' => 0,
            'mata_kuliah_count' => 0,
        ];
    }
}
