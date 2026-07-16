<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Referensi;
use App\Services\Ai\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Pustaka/Referensi per Mata Kuliah (kode_mk). Menjadi rujukan "Pustaka Utama
 * & Pendukung" pada RPS dan grounding pustaka saat generate
 * (RpsGeneratorService::pustakaContext). Diedit dari modal Mata Kuliah.
 */
class ReferensiController extends Controller
{
    public function __construct(private AiService $ai) {}

    /** Daftar referensi satu MK (filter institusi_id + kode_mk). */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'institusi_id' => ['required', 'integer'],
            'kode_mk'      => ['required', 'string', 'max:50'],
        ]);

        $items = Referensi::query()
            ->where('institusi_id', $data['institusi_id'])
            ->where('kode_mk', $data['kode_mk'])
            ->orderByRaw("CASE WHEN tipe = 'utama' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get(['id', 'institusi_id', 'kode_mk', 'tipe', 'sitasi']);

        return response()->json(['data' => $items]);
    }

    /**
     * Ganti-total (replace-all) daftar referensi satu MK sekaligus — cocok
     * dengan editor daftar di modal (simpan satu kali). Transaksional.
     */
    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'institusi_id'  => ['required', 'integer'],
            'kode_mk'       => ['required', 'string', 'max:50'],
            'items'         => ['present', 'array', 'max:100'],
            'items.*.tipe'  => ['required', Rule::in(['utama', 'pendukung'])],
            'items.*.sitasi' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($data) {
            Referensi::where('institusi_id', $data['institusi_id'])
                ->where('kode_mk', $data['kode_mk'])
                ->delete();

            foreach ($data['items'] as $it) {
                Referensi::create([
                    'institusi_id' => $data['institusi_id'],
                    'kode_mk'      => $data['kode_mk'],
                    'tipe'         => $it['tipe'],
                    'sitasi'       => trim($it['sitasi']),
                ]);
            }
        });

        return $this->index($request);
    }

    /**
     * Saran pustaka via AI — bersifat DRAFT (berpotensi halusinasi judul/penulis),
     * WAJIB diverifikasi dosen. Mengembalikan array {tipe, sitasi} tanpa menyimpan.
     */
    public function suggest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'institusi_id' => ['required', 'integer'],
            'kode_mk'      => ['nullable', 'string', 'max:50'],
            'nama'         => ['required', 'string', 'max:200'],
            'jenis'        => ['nullable', 'string', 'max:50'],
            'sks'          => ['nullable', 'integer'],
            'deskripsi'    => ['nullable', 'string', 'max:2000'],
        ]);

        $system = 'Anda pustakawan akademik untuk kurikulum farmasi/kesehatan berbahasa Indonesia. '
            . 'Sarankan daftar pustaka (buku/jurnal standar) yang RELEVAN dan LAZIM dipakai untuk mata '
            . 'kuliah yang diberikan. Utamakan sumber yang benar-benar ada dan umum dikenal; JANGAN '
            . 'mengarang judul, penulis, atau ISBN. Gunakan format sitasi ringkas: Penulis (Tahun). Judul. Penerbit. '
            . 'Balas HANYA JSON array of objects dengan kunci "tipe" ("utama"|"pendukung") dan "sitasi" (string), '
            . 'tanpa penjelasan atau pagar markdown. Maksimal 6 item (3 utama, 3 pendukung).';

        $fakta = array_filter([
            'nama_mk'   => $data['nama'],
            'jenis_mk'  => $data['jenis'] ?? null,
            'sks'       => $data['sks'] ?? null,
            'deskripsi' => $data['deskripsi'] ?? null,
        ]);
        $prompt = "Mata kuliah:\n" . json_encode($fakta, JSON_UNESCAPED_UNICODE)
            . "\n\nSaran pustaka utama & pendukung:";

        $outcome = $this->ai->run('asistif', $system, $prompt, [
            'institusi_id' => (int) $data['institusi_id'],
            'entity_type'  => 'referensi_suggest',
            'mode'         => 'referensi:suggest',
        ]);

        if ($outcome->failed()) {
            return response()->json(['message' => 'Layanan AI tidak tersedia saat ini.'], 503);
        }

        $items = $this->parseSuggestions($outcome->text());

        return response()->json(['data' => $items]);
    }

    /**
     * Ekstrak array {tipe, sitasi} dari keluaran model (toleran terhadap pagar
     * markdown / teks pembungkus). Kembalikan hanya item valid.
     *
     * @return array<int,array{tipe:string,sitasi:string}>
     */
    private function parseSuggestions(string $text): array
    {
        $clean = trim($text);
        $clean = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $clean);
        $clean = trim((string) $clean);

        $decoded = json_decode($clean, true);
        if (! is_array($decoded)) {
            $start = strpos($clean, '[');
            $end = strrpos($clean, ']');
            if ($start !== false && $end !== false && $end > $start) {
                $decoded = json_decode(substr($clean, $start, $end - $start + 1), true);
            }
        }

        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->map(function ($it) {
                if (! is_array($it)) {
                    return null;
                }
                $tipe = strtolower((string) ($it['tipe'] ?? 'utama'));
                $sitasi = trim((string) ($it['sitasi'] ?? ''));
                if ($sitasi === '') {
                    return null;
                }
                return [
                    'tipe'   => in_array($tipe, ['utama', 'pendukung'], true) ? $tipe : 'utama',
                    'sitasi' => $sitasi,
                ];
            })
            ->filter()
            ->take(12)
            ->values()
            ->all();
    }
}
