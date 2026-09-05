<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProdiVmts;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CRUD VMTS (Visi, Misi, Tujuan, Strategi) prodi — berversi.
 * misi/tujuan/strategi = daftar bernomor (array item). Satu prodi boleh punya
 * beberapa versi; kurikulum memilih salah satu (kurikulum.vmts_id).
 */
class ProdiVmtsController extends Controller
{
    public function index(Request $request)
    {
        $query = ProdiVmts::query()->orderByDesc('id');
        $this->applyTenantScope($query, $request);

        return response()->json(['data' => $query->get()->map(fn($v) => $this->format($v))]);
    }

    public function store(Request $request)
    {
        $data = $this->validasi($request);
        $vmts = ProdiVmts::create($data);

        return response()->json(['data' => $this->format($vmts)], 201);
    }

    public function update(Request $request, ProdiVmts $prodiVmts)
    {
        $prodiVmts->update($this->validasi($request, $prodiVmts));

        return response()->json(['data' => $this->format($prodiVmts->fresh())]);
    }

    public function destroy(ProdiVmts $prodiVmts)
    {
        $prodiVmts->delete();

        return response()->json(['message' => 'Versi VMTS dihapus.']);
    }

    private function validasi(Request $request, ?ProdiVmts $existing = null): array
    {
        $data = $request->validate([
            'institusi_id' => [$existing ? 'sometimes' : 'required', 'integer', Rule::exists('institusi', 'id')->where('jenis', 'prodi')],
            'label'        => [$existing ? 'sometimes' : 'required', 'string', 'max:255'],
            'visi'         => ['nullable', 'string'],
            'misi'         => ['nullable', 'array'],
            'misi.*'       => ['string'],
            'tujuan'       => ['nullable', 'array'],
            'tujuan.*'     => ['string'],
            'strategi'     => ['nullable', 'array'],
            'strategi.*'   => ['string'],
        ]);

        // Bersihkan item kosong pada daftar bernomor.
        foreach (['misi', 'tujuan', 'strategi'] as $key) {
            if (isset($data[$key])) {
                $data[$key] = array_values(array_filter(array_map(
                    fn($s) => trim((string) $s),
                    $data[$key]
                ), fn($s) => $s !== ''));
            }
        }

        return $data;
    }

    private function format(ProdiVmts $v): array
    {
        return [
            'id'           => $v->id,
            'institusi_id' => $v->institusi_id,
            'label'        => $v->label,
            'visi'         => $v->visi,
            'misi'         => $v->misi ?? [],
            'tujuan'       => $v->tujuan ?? [],
            'strategi'     => $v->strategi ?? [],
        ];
    }
}
