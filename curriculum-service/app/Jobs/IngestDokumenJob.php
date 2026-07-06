<?php

namespace App\Jobs;

use App\Models\DokumenRujukan;
use App\Services\Doc\DocumentIngestService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Indexing dokumen rujukan (ekstraksi -> chunk -> embedding) dijalankan di
 * latar belakang agar request upload tidak memblok (dokumen besar bisa lama
 * dan melebihi batas timeout proxy/Cloudflare).
 */
class IngestDokumenJob implements ShouldQueue
{
    use Queueable;

    /** Indexing dokumen besar bisa lama (banyak panggilan embedding). */
    public int $timeout = 1800;

    /** Idempoten (ingest menghapus chunk lama dulu), tapi cukup 1x agar tak dobel biaya. */
    public int $tries = 1;

    public function __construct(public int $dokumenId) {}

    public function handle(DocumentIngestService $ingest): void
    {
        $dokumen = DokumenRujukan::find($this->dokumenId);
        if (! $dokumen) {
            return;
        }

        $ingest->ingest($dokumen);
    }

    public function failed(Throwable $e): void
    {
        DokumenRujukan::whereKey($this->dokumenId)->update(['status_indexing' => 'error']);
    }
}
