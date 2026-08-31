<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AppliesSorting;
use App\Http\Controllers\Controller;
use App\Http\Resources\DokumenRujukanResource;
use App\Jobs\IngestDokumenJob;
use App\Models\DokumenRujukan;
use App\Models\MataKuliah;
use App\Models\MkDokumenRujukan;
use App\Services\Ai\EmbeddingService;
use Illuminate\Http\Request;

class DokumenRujukanController extends Controller
{
    use AppliesSorting;

    public function __construct(
        private EmbeddingService $embeddings,
    ) {}

    public function index(Request $request)
    {
        $query = DokumenRujukan::query()->with('badanRujukan')->withCount(['chunks', 'mataKuliahTautan']);

        if ($request->filled('institusi_id')) {
            $query->where('institusi_id', $request->integer('institusi_id'));
        }
        if ($request->filled('kode_mk')) {
            $query->whereHas('mataKuliahTautan', fn($q) => $q->where('kode_mk', $request->string('kode_mk')));
        }
        if ($request->filled('badan_rujukan_id')) {
            $query->where('badan_rujukan_id', $request->integer('badan_rujukan_id'));
        }
        if ($request->filled('jenis')) {
            $query->where('jenis', $request->string('jenis'));
        }
        if ($request->filled('status')) {
            $query->where('status_indexing', $request->string('status'));
        }
        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where('judul', 'like', "%{$q}%");
        }

        $this->applySort($query, $request, ['judul', 'jenis', 'status_indexing', 'sumber_konten', 'chunk_count', 'created_at'], 'created_at', 'desc');

        return DokumenRujukanResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'institusi_id'     => ['required', 'exists:institusi,id'],
            'badan_rujukan_id' => ['nullable', 'exists:badan_rujukan,id'],
            'jenis'            => ['required', 'in:kpt,asosiasi,akreditasi,template_rps,buku'],
            'sumber_konten'    => ['nullable', 'boolean'],
            'judul'            => ['nullable', 'string', 'max:255'],
            'kode_mk'          => ['nullable', 'string', 'max:64'], // auto-tautkan ke MK setelah simpan
            'file'             => ['required', 'file', 'mimes:pdf,docx,txt,md,csv', 'max:51200'],
        ]);

        $file = $request->file('file');
        $kodeMk = trim((string) ($data['kode_mk'] ?? '')) ?: null;

        // Dedup: berkas identik (sha256) di institusi yang sama tidak disimpan dua
        // kali. Bila ada kode_mk, dokumen lama cukup DITAUTKAN ke MK tsb.
        $hash = hash_file('sha256', $file->getRealPath());
        $sudahAda = DokumenRujukan::query()
            ->where('institusi_id', $data['institusi_id'])
            ->where('file_hash', $hash)
            ->first();
        if ($sudahAda) {
            if ($kodeMk) {
                $this->buatTautan($sudahAda, $kodeMk);

                return (new DokumenRujukanResource($sudahAda->fresh()->load('badanRujukan')->loadCount(['chunks', 'mataKuliahTautan'])))
                    ->additional(['message' => "Berkas ini sudah pernah diunggah (\"{$sudahAda->judul}\") — dokumen lama langsung ditautkan ke MK {$kodeMk} tanpa duplikasi."])
                    ->response()
                    ->setStatusCode(200);
            }

            return (new DokumenRujukanResource($sudahAda->load('badanRujukan')->loadCount(['chunks', 'mataKuliahTautan'])))
                ->additional(['message' => "Berkas identik sudah ada sebagai \"{$sudahAda->judul}\" — gunakan dokumen tersebut, tidak perlu mengunggah ulang."])
                ->response()
                ->setStatusCode(409);
        }

        $path = $file->store('dokumen-rujukan', 'local');

        $dokumen = DokumenRujukan::create([
            'institusi_id'     => $data['institusi_id'],
            'badan_rujukan_id' => $data['badan_rujukan_id'] ?? null,
            'jenis'            => $data['jenis'],
            'sumber_konten'    => (bool) ($data['sumber_konten'] ?? false),
            'judul'            => $data['judul'] ?? $file->getClientOriginalName(),
            'file_asal'        => $file->getClientOriginalName(),
            'file_path'        => $path,
            'file_hash'        => $hash,
            'status_indexing'  => 'pending',
        ]);

        if ($kodeMk) {
            $this->buatTautan($dokumen, $kodeMk);
        }

        // Indexing (ekstraksi + embedding) dijalankan di latar belakang agar
        // request upload balas cepat dan tidak kena timeout proxy/Cloudflare
        // untuk dokumen besar. Status berubah 'pending' -> 'indexed'/'error'.
        IngestDokumenJob::dispatch($dokumen->id);

        return (new DokumenRujukanResource($dokumen->fresh()->load('badanRujukan')->loadCount(['chunks', 'mataKuliahTautan'])))
            ->additional(['message' => 'Dokumen diunggah. Indexing berjalan di latar belakang.'])
            ->response()
            ->setStatusCode(201);
    }

    /** Daftar MK yang menautkan dokumen ini (+nama MK bila ada di master). */
    public function mataKuliahIndex(DokumenRujukan $dokumenRujukan)
    {
        $tautan = $dokumenRujukan->mataKuliahTautan()->orderBy('kode_mk')->get();
        $nama = MataKuliah::query()
            ->where('institusi_id', $dokumenRujukan->institusi_id)
            ->whereIn('kode_mk', $tautan->pluck('kode_mk'))
            ->pluck('nama', 'kode_mk');

        return response()->json([
            'data' => $tautan->map(fn($t) => [
                'id'      => $t->id,
                'kode_mk' => $t->kode_mk,
                'nama'    => $nama[$t->kode_mk] ?? null,
            ])->values(),
        ]);
    }

    /** Tautkan dokumen ke satu MK (idempoten). */
    public function mataKuliahAttach(Request $request, DokumenRujukan $dokumenRujukan)
    {
        $data = $request->validate(['kode_mk' => ['required', 'string', 'max:64']]);

        $adaMk = MataKuliah::query()
            ->where('institusi_id', $dokumenRujukan->institusi_id)
            ->where('kode_mk', $data['kode_mk'])
            ->exists();
        if (! $adaMk) {
            return response()->json(['message' => "Mata kuliah {$data['kode_mk']} tidak ditemukan di institusi ini."], 422);
        }

        $this->buatTautan($dokumenRujukan, $data['kode_mk']);

        return response()->json(['message' => "Dokumen ditautkan ke MK {$data['kode_mk']}."]);
    }

    /** Lepas tautan dokumen dari satu MK. */
    public function mataKuliahDetach(DokumenRujukan $dokumenRujukan, string $kodeMk)
    {
        $dokumenRujukan->mataKuliahTautan()->where('kode_mk', $kodeMk)->delete();

        return response()->noContent();
    }

    private function buatTautan(DokumenRujukan $dokumen, string $kodeMk): void
    {
        MkDokumenRujukan::firstOrCreate([
            'institusi_id'       => $dokumen->institusi_id,
            'kode_mk'            => $kodeMk,
            'dokumen_rujukan_id' => $dokumen->id,
        ]);
    }

    public function show(DokumenRujukan $dokumenRujukan)
    {
        return new DokumenRujukanResource($dokumenRujukan->load('badanRujukan')->loadCount('chunks'));
    }

    /**
     * Perbarui metadata dokumen — terutama toggle 'sumber_konten' (dokumen
     * keilmuan vs rujukan format). Hanya dokumen keilmuan yang dipakai AI
     * sebagai sumber substansi generate & bukti grounding.
     */
    public function update(Request $request, DokumenRujukan $dokumenRujukan)
    {
        $data = $request->validate([
            'judul'            => ['sometimes', 'nullable', 'string', 'max:255'],
            'jenis'            => ['sometimes', 'in:kpt,asosiasi,akreditasi,template_rps,buku'],
            'badan_rujukan_id' => ['sometimes', 'nullable', 'exists:badan_rujukan,id'],
            'sumber_konten'    => ['sometimes', 'boolean'],
        ]);

        $dokumenRujukan->update($data);

        return new DokumenRujukanResource($dokumenRujukan->fresh()->load('badanRujukan')->loadCount('chunks'));
    }

    public function reindex(DokumenRujukan $dokumenRujukan)
    {
        $dokumenRujukan->update(['status_indexing' => 'pending']);
        IngestDokumenJob::dispatch($dokumenRujukan->id);

        return (new DokumenRujukanResource($dokumenRujukan->fresh()->loadCount('chunks')))
            ->additional(['message' => 'Indexing ulang dijadwalkan di latar belakang.']);
    }

    public function search(Request $request)
    {
        $data = $request->validate([
            'institusi_id' => ['required', 'integer'],
            'query'        => ['required', 'string', 'max:2000'],
            'top_k'        => ['nullable', 'integer', 'min:1', 'max:20'],
            'dokumen_id'   => ['nullable', 'integer'],
        ]);

        $hits = $this->embeddings->search(
            (int) $data['institusi_id'],
            $data['query'],
            (int) ($data['top_k'] ?? 5),
            array_filter(['dokumen_id' => $data['dokumen_id'] ?? null], fn($v) => $v !== null),
        );

        return response()->json([
            'data' => collect($hits)->map(fn($h) => [
                'dokumen_id' => $h['chunk']->dokumen_id,
                'urutan'     => $h['chunk']->urutan,
                'score'      => round($h['score'], 4),
                'teks'       => mb_strimwidth($h['chunk']->teks, 0, 400, '…'),
            ])->values(),
        ]);
    }

    public function destroy(DokumenRujukan $dokumenRujukan)
    {
        if ($dokumenRujukan->file_path) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($dokumenRujukan->file_path);
        }
        $dokumenRujukan->chunks()->delete();
        $dokumenRujukan->delete();

        return response()->noContent();
    }
}
