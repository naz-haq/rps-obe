<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\KonfigurasiAturanResource;
use App\Models\KonfigurasiAturan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Modul 1 — KONFIGURASI_ATURAN (diisi manual oleh admin/Kaprodi).
 * Nilai aturan otoritatif (jumlah minggu, bobot total, konversi SKS->jam) yang
 * sengaja TIDAK didistilasi AI demi akurasi. Satu record per (institusi, jenis).
 */
class KonfigurasiAturanController extends Controller
{
    /** Jenis aturan yang dikenal (whitelist). */
    public const JENIS = ['jumlah_minggu', 'bobot_teori', 'bobot_praktikum', 'konversi_sks', 'konversi_minggu_profesi', 'mode_distribusi_waktu', 'durasi_sesi'];

    /** Daftar konfigurasi untuk satu institusi. */
    public function index(Request $request): JsonResponse
    {
        $institusiId = $request->integer('institusi_id', 1);

        // Mode "efektif": kembalikan aturan yang BERLAKU untuk institusi ini
        // dengan pewarisan ke atas (prodi → fakultas → universitas → global NULL),
        // yang paling spesifik menang. Dipakai banner prasyarat generator agar
        // tak salah lapor "belum diatur" saat aturan diset di tingkat atas.
        if ($request->boolean('efektif')) {
            $rantai = \App\Models\Institusi::idsHierarkiKeAtas($institusiId);
            $peringkat = array_flip($rantai);
            $rows = KonfigurasiAturan::query()
                ->where(function ($q) use ($rantai) {
                    $q->whereNull('institusi_id')->orWhereIn('institusi_id', $rantai);
                })
                ->get();
            $terpilih = $rows
                ->groupBy('jenis_aturan')
                ->map(fn($grup) => $grup->sortBy(fn($r) => $r->institusi_id === null
                    ? PHP_INT_MAX
                    : ($peringkat[$r->institusi_id] ?? PHP_INT_MAX - 1))->first())
                ->values();

            return response()->json([
                'data' => KonfigurasiAturanResource::collection($terpilih),
            ]);
        }

        $items = KonfigurasiAturan::where('institusi_id', $institusiId)
            ->orderBy('jenis_aturan')
            ->get();

        return response()->json([
            'data' => KonfigurasiAturanResource::collection($items),
        ]);
    }

    /** Simpan/perbarui satu jenis aturan (updateOrCreate by institusi+jenis). */
    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'institusi_id'         => ['required', 'integer', 'exists:institusi,id'],
            'jenis_aturan'         => ['required', Rule::in(self::JENIS)],
            'nilai'                => ['required', 'array'],
            'badan_rujukan_id'     => ['nullable', 'integer', 'exists:badan_rujukan,id'],
            'referensi_dokumen_id' => ['nullable', 'integer', 'exists:dokumen_rujukan,id'],
            'referensi_halaman'    => ['nullable', 'integer', 'min:1'],
        ]);

        $record = KonfigurasiAturan::updateOrCreate(
            [
                'institusi_id' => $data['institusi_id'],
                'jenis_aturan' => $data['jenis_aturan'],
            ],
            [
                'nilai'                => $data['nilai'],
                'badan_rujukan_id'     => $data['badan_rujukan_id'] ?? null,
                'referensi_dokumen_id' => $data['referensi_dokumen_id'] ?? null,
                'referensi_halaman'    => $data['referensi_halaman'] ?? null,
            ],
        );

        return response()->json(
            ['data' => new KonfigurasiAturanResource($record)],
            $record->wasRecentlyCreated ? 201 : 200,
        );
    }

    /** Hapus satu konfigurasi aturan. */
    public function destroy(KonfigurasiAturan $konfigurasiAturan): JsonResponse
    {
        $konfigurasiAturan->delete();

        return response()->json(['message' => 'Konfigurasi aturan dihapus.']);
    }
}
