<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Modul 2 — AI Asistif Inline (fitur #2 Blueprint).
 * Membantu menyunting SATU field (perbaiki/parafrase/ringkas/panjangkan).
 * Bersifat non-otoritatif & non-mengarang: hanya menyusun ulang teks yang
 * sudah ditulis manusia; tidak menambah fakta baru. Dosen tetap memutuskan.
 */
class AiAsistifController extends Controller
{
    public function __construct(private AiService $ai) {}

    /** Peta mode -> instruksi ringkas untuk model. */
    private const MODE = [
        'perbaiki'   => 'Perbaiki ejaan, tata bahasa, dan kejelasan kalimat berikut tanpa mengubah makna atau menambah fakta baru.',
        'parafrase'  => 'Parafrasekan kalimat berikut agar lebih baik dan formal, tetap mempertahankan makna dan tidak menambah fakta baru.',
        'ringkas'    => 'Ringkas kalimat berikut menjadi lebih padat namun tetap mempertahankan makna inti; jangan menambah fakta baru.',
        'panjangkan' => 'Uraikan kalimat berikut menjadi lebih lengkap dan jelas sesuai konteks yang sudah ada; jangan mengarang fakta baru.',
        'generate'   => 'Susun/tuliskan isi field ini dari awal, akademik dan lugas, BERDASARKAN informasi konteks yang diberikan. '
            . 'Boleh menyusun kalimat baru, tetapi JANGAN mengarang angka, nama, atau klaim spesifik yang tidak ada pada konteks.',
    ];

    /** Mode yang menghasilkan konten baru (boleh tanpa teks awal). */
    private const MODE_GENERATIF = ['generate'];

    public function asistif(Request $request): JsonResponse
    {
        $data = $request->validate([
            'institusi_id' => ['required', 'integer'],
            'mode'         => ['required', Rule::in(array_keys(self::MODE))],
            'teks'         => ['nullable', 'string', 'max:5000'],
            'konteks'      => ['nullable', 'string', 'max:120'],
            'data'         => ['nullable', 'string', 'max:2000'],
        ]);

        $generatif = in_array($data['mode'], self::MODE_GENERATIF, true);

        // Mode penyuntingan wajib punya teks; mode generatif tidak.
        if (! $generatif && trim((string) ($data['teks'] ?? '')) === '') {
            return response()->json([
                'message' => 'Teks kosong. Isi teks dulu untuk mode penyuntingan.',
            ], 422);
        }

        // System prompt bercabang: penyuntingan = larangan fakta baru ketat;
        // generatif = boleh menyusun prosa dari fakta konteks, tetap tanpa
        // mengarang angka/nama spesifik.
        $system = $generatif
            ? 'Anda asisten penulisan akademik untuk dokumen kurikulum OBE berbahasa Indonesia. '
            . 'Tugas Anda menyusun isi satu field dari awal berdasarkan konteks/fakta yang diberikan. '
            . 'Aturan: gunakan bahasa akademik yang lugas dan ringkas; jangan mengarang angka, nama, '
            . 'atau klaim spesifik di luar konteks. Balas HANYA dengan teks hasilnya, tanpa tanda kutip, '
            . 'label, atau penjelasan apa pun.'
            : 'Anda asisten penulisan akademik untuk dokumen kurikulum OBE berbahasa Indonesia. '
            . 'Tugas Anda hanya menyunting redaksi satu field. Aturan ketat: JANGAN menambah fakta, '
            . 'angka, atau klaim baru; pertahankan makna asli; gunakan bahasa akademik yang lugas. '
            . 'Balas HANYA dengan teks hasil suntingan, tanpa tanda kutip, label, atau penjelasan apa pun.';

        $konteks = $data['konteks'] ? "Konteks field: {$data['konteks']}.\n" : '';
        $fakta = ! empty($data['data']) ? "Informasi konteks:\n{$data['data']}\n\n" : '';
        $teks = trim((string) ($data['teks'] ?? ''));

        $prompt = $konteks . $fakta . self::MODE[$data['mode']]
            . ($teks !== '' ? "\n\nTeks:\n" . $teks : '');

        $outcome = $this->ai->run('asistif', $system, $prompt, [
            'institusi_id' => (int) $data['institusi_id'],
            'entity_type'  => 'field_asistif',
            'mode'         => 'asistif:' . $data['mode'],
        ]);

        if ($outcome->failed()) {
            return response()->json([
                'message' => 'Layanan AI tidak tersedia saat ini.',
            ], 503);
        }

        return response()->json([
            'data' => ['teks' => trim($outcome->text())],
        ]);
    }
}
