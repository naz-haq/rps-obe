<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AppliesSorting;
use App\Http\Controllers\Controller;
use App\Http\Resources\DokumenRujukanResource;
use App\Jobs\IngestDokumenJob;
use App\Models\DokumenRujukan;
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
        $query = DokumenRujukan::query()->with('badanRujukan')->withCount('chunks');

        if ($request->filled('institusi_id')) {
            $query->where('institusi_id', $request->integer('institusi_id'));
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

        $this->applySort($query, $request, ['judul', 'jenis', 'status_indexing', 'chunk_count', 'created_at'], 'created_at', 'desc');

        return DokumenRujukanResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'institusi_id'     => ['required', 'exists:institusi,id'],
            'badan_rujukan_id' => ['nullable', 'exists:badan_rujukan,id'],
            'jenis'            => ['required', 'in:kpt,asosiasi,akreditasi,template_rps'],
            'judul'            => ['nullable', 'string', 'max:255'],
            'file'             => ['required', 'file', 'mimes:pdf,docx,txt,md,csv', 'max:51200'],
        ]);

        $file = $request->file('file');
        $path = $file->store('dokumen-rujukan', 'local');

        $dokumen = DokumenRujukan::create([
            'institusi_id'     => $data['institusi_id'],
            'badan_rujukan_id' => $data['badan_rujukan_id'] ?? null,
            'jenis'            => $data['jenis'],
            'judul'            => $data['judul'] ?? $file->getClientOriginalName(),
            'file_asal'        => $file->getClientOriginalName(),
            'file_path'        => $path,
            'status_indexing'  => 'pending',
        ]);

        // Indexing (ekstraksi + embedding) dijalankan di latar belakang agar
        // request upload balas cepat dan tidak kena timeout proxy/Cloudflare
        // untuk dokumen besar. Status berubah 'pending' -> 'indexed'/'error'.
        IngestDokumenJob::dispatch($dokumen->id);

        return (new DokumenRujukanResource($dokumen->fresh()->load('badanRujukan')->loadCount('chunks')))
            ->additional(['message' => 'Dokumen diunggah. Indexing berjalan di latar belakang.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(DokumenRujukan $dokumenRujukan)
    {
        return new DokumenRujukanResource($dokumenRujukan->load('badanRujukan')->loadCount('chunks'));
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
