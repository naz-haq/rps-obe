<?php

namespace App\Services\Doc;

use App\Models\DokumenChunk;
use App\Models\DokumenRujukan;
use App\Services\Ai\EmbeddingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Ingest dokumen rujukan: ekstraksi teks -> chunk -> embedding.
 * Menandai status_indexing dokumen (pending/indexed/error).
 */
class DocumentIngestService
{
    /** Ukuran chunk (karakter) & overlap. */
    private const CHUNK_SIZE = 1200;
    private const CHUNK_OVERLAP = 150;

    public function __construct(
        private DocumentTextExtractor $extractor,
        private EmbeddingService $embeddings,
    ) {}

    /**
     * Proses satu dokumen yang file_path-nya sudah tersimpan (disk local).
     * Menghapus chunk lama bila re-index.
     *
     * @return array{chunks:int, pages:int, status:string}
     */
    public function ingest(DokumenRujukan $dokumen): array
    {
        $relPath = $dokumen->file_path;
        if (! $relPath || ! Storage::disk('local')->exists($relPath)) {
            $dokumen->update(['status_indexing' => 'error']);
            throw new \RuntimeException('Berkas dokumen tidak ditemukan.');
        }

        $absPath = Storage::disk('local')->path($relPath);
        $ext = pathinfo($dokumen->file_asal ?? $relPath, PATHINFO_EXTENSION);

        try {
            $dokumen->update(['status_indexing' => 'pending']);
            $dokumen->chunks()->delete();

            $extracted = $this->extractor->extract($absPath, $ext);
            $chunks = $this->chunk($extracted['text']);

            $urutan = 0;
            foreach ($chunks as $teks) {
                $chunk = DokumenChunk::create([
                    'dokumen_id' => $dokumen->id,
                    'urutan'     => $urutan++,
                    'teks'       => $teks,
                ]);
                $this->embeddings->embedChunk($chunk, ['institusi_id' => $dokumen->institusi_id]);
            }

            $dokumen->update([
                'status_indexing'  => 'indexed',
                'vector_namespace' => 'inst:' . $dokumen->institusi_id,
            ]);

            return ['chunks' => count($chunks), 'pages' => $extracted['pages'], 'status' => 'indexed'];
        } catch (Throwable $e) {
            Log::warning('Ingest dokumen gagal', ['dokumen_id' => $dokumen->id, 'error' => $e->getMessage()]);
            $dokumen->update(['status_indexing' => 'error']);
            throw $e;
        }
    }

    /**
     * Pecah teks menjadi chunk dengan overlap, memperhatikan batas paragraf.
     *
     * @return array<int,string>
     */
    public function chunk(string $text): array
    {
        $text = preg_replace('/[ \t]+/', ' ', trim($text)) ?? '';
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? '';

        if ($text === '') {
            return [];
        }

        if (mb_strlen($text) <= self::CHUNK_SIZE) {
            return [$text];
        }

        $chunks = [];
        $len = mb_strlen($text);
        $start = 0;

        while ($start < $len) {
            $end = min($start + self::CHUNK_SIZE, $len);

            // Coba potong di batas kalimat/paragraf terdekat sebelum $end.
            if ($end < $len) {
                $slice = mb_substr($text, $start, $end - $start);
                $breakPos = max(
                    mb_strrpos($slice, "\n") ?: 0,
                    mb_strrpos($slice, '. ') ?: 0,
                );
                if ($breakPos > self::CHUNK_SIZE * 0.5) {
                    $end = $start + $breakPos + 1;
                }
            }

            $piece = trim(mb_substr($text, $start, $end - $start));
            if ($piece !== '') {
                $chunks[] = $piece;
            }

            if ($end >= $len) {
                break;
            }
            $start = max($end - self::CHUNK_OVERLAP, $start + 1);
        }

        return $chunks;
    }
}
