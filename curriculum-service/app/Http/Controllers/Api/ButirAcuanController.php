<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ButirAcuanResource;
use App\Models\ButirAcuan;
use App\Models\KerangkaAcuan;
use Illuminate\Http\Request;

class ButirAcuanController extends Controller
{
    private const KATEGORI = 'in:profil_lulusan,cpl,bahan_kajian,kriteria_akreditasi,struktur,aturan';

    public function store(Request $request, KerangkaAcuan $kerangkaAcuan)
    {
        $data = $request->validate([
            'parent_id' => ['nullable', 'exists:butir_acuan,id'],
            'kategori'  => ['required', self::KATEGORI],
            'kode'      => ['nullable', 'string', 'max:255'],
            'deskripsi' => ['required', 'string'],
            'wajib'     => ['nullable', 'boolean'],
            'urutan'    => ['nullable', 'integer', 'min:0'],
        ]);

        $data['kerangka_acuan_id'] = $kerangkaAcuan->id;
        $data['wajib'] = $data['wajib'] ?? true;
        $data['urutan'] = $data['urutan'] ?? ($kerangkaAcuan->butir()->max('urutan') + 1);

        $butir = ButirAcuan::create($data);

        return (new ButirAcuanResource($butir))->response()->setStatusCode(201);
    }

    public function update(Request $request, ButirAcuan $butirAcuan)
    {
        $data = $request->validate([
            'parent_id' => ['nullable', 'exists:butir_acuan,id'],
            'kategori'  => ['sometimes', 'required', self::KATEGORI],
            'kode'      => ['nullable', 'string', 'max:255'],
            'deskripsi' => ['sometimes', 'required', 'string'],
            'wajib'     => ['nullable', 'boolean'],
            'urutan'    => ['nullable', 'integer', 'min:0'],
        ]);

        $butirAcuan->update($data);

        return new ButirAcuanResource($butirAcuan);
    }

    public function destroy(ButirAcuan $butirAcuan)
    {
        $butirAcuan->delete();

        return response()->noContent();
    }
}
