<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dosen;
use App\Models\MkPengampu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Dosen Pengampu per Mata Kuliah (mk_pengampu). Ditampilkan pada header RPS
 * cetak/DOCX. Diedit dari panel Detail MK oleh dosen. Menyimpan juga nama ke
 * master `dosen` (upsert per NIDN) agar cetakan menampilkan nama, bukan NIDN.
 */
class MkPengampuController extends Controller
{
    /** Daftar pengampu satu MK (institusi_id + kode_mk), lengkap dgn nama. */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'institusi_id' => ['required', 'integer'],
            'kode_mk'      => ['required', 'string', 'max:50'],
        ]);

        $rows = MkPengampu::query()
            ->where('institusi_id', $data['institusi_id'])
            ->where('kode_mk', $data['kode_mk'])
            ->orderByRaw("CASE WHEN peran = 'koordinator' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get(['dosen_nidn', 'peran']);

        $nama = Dosen::where('institusi_id', $data['institusi_id'])
            ->whereIn('nidn', $rows->pluck('dosen_nidn'))
            ->pluck('nama', 'nidn');

        return response()->json(['data' => $rows->map(fn($r) => [
            'nidn'  => $r->dosen_nidn,
            'nama'  => $nama[$r->dosen_nidn] ?? $r->dosen_nidn,
            'peran' => $r->peran,
        ])]);
    }

    /**
     * Ganti-total (replace-all) daftar pengampu satu MK. Upsert nama ke master
     * `dosen` per NIDN, lalu tulis ulang mk_pengampu. Transaksional.
     */
    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'institusi_id'  => ['required', 'integer'],
            'kode_mk'       => ['required', 'string', 'max:50'],
            'items'         => ['present', 'array', 'max:20'],
            'items.*.nidn'  => ['required', 'string', 'max:50'],
            'items.*.nama'  => ['required', 'string', 'max:150'],
            'items.*.peran' => ['required', Rule::in(['koordinator', 'anggota'])],
        ]);

        DB::transaction(function () use ($data) {
            MkPengampu::where('institusi_id', $data['institusi_id'])
                ->where('kode_mk', $data['kode_mk'])
                ->delete();

            $seen = [];
            foreach ($data['items'] as $it) {
                $nidn = trim($it['nidn']);
                if ($nidn === '' || isset($seen[$nidn])) {
                    continue;
                }
                $seen[$nidn] = true;

                Dosen::updateOrCreate(
                    ['institusi_id' => $data['institusi_id'], 'nidn' => $nidn],
                    ['nama' => trim($it['nama'])],
                );
                MkPengampu::create([
                    'institusi_id' => $data['institusi_id'],
                    'kode_mk'      => $data['kode_mk'],
                    'dosen_nidn'   => $nidn,
                    'peran'        => $it['peran'],
                ]);
            }
        });

        return $this->index($request);
    }
}
