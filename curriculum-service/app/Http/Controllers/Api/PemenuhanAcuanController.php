<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PemenuhanAcuan;
use Illuminate\Http\Request;

class PemenuhanAcuanController extends Controller
{
    /** Set/ubah status pemenuhan satu butir untuk satu institusi. */
    public function upsert(Request $request)
    {
        $data = $request->validate([
            'institusi_id'   => ['required', 'exists:institusi,id'],
            'butir_acuan_id' => ['required', 'exists:butir_acuan,id'],
            'status'         => ['required', 'in:terpenuhi,sebagian,belum,tidak_relevan'],
            'catatan'        => ['nullable', 'string'],
            'entity_type'    => ['nullable', 'string', 'max:255'],
            'entity_id'      => ['nullable', 'integer'],
        ]);

        $pemenuhan = PemenuhanAcuan::updateOrCreate(
            [
                'institusi_id'   => $data['institusi_id'],
                'butir_acuan_id' => $data['butir_acuan_id'],
            ],
            [
                'status'      => $data['status'],
                'catatan'     => $data['catatan'] ?? null,
                'entity_type' => $data['entity_type'] ?? null,
                'entity_id'   => $data['entity_id'] ?? null,
            ],
        );

        return response()->json([
            'data' => [
                'id'             => $pemenuhan->id,
                'butir_acuan_id' => $pemenuhan->butir_acuan_id,
                'status'         => $pemenuhan->status,
                'catatan'        => $pemenuhan->catatan,
            ],
        ]);
    }
}
