<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kurikulum;
use App\Services\Kurikulum\BukuKurikulumBuilder;
use App\Services\Kurikulum\BukuKurikulumDocxExporter;
use App\Services\Kurikulum\BukuKurikulumNaratifService;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\IOFactory;

/**
 * Modul Buku Kurikulum: rakit dokumen kurikulum prodi (deterministik) + ekspor
 * .docx. Diblokir bila ada Mata Kuliah yang belum memiliki RPS ter-commit.
 */
class KurikulumBukuController extends Controller
{
    public function __construct(private BukuKurikulumBuilder $builder) {}

    /** Status kelengkapan prasyarat (semua MK wajib punya RPS). */
    public function kelengkapan(Kurikulum $kurikulum)
    {
        return response()->json(['data' => $this->builder->kelengkapan($kurikulum)]);
    }

    /** Pratinjau struktur Buku Kurikulum (JSON) — hormati blokir kelengkapan. */
    public function pratinjau(Kurikulum $kurikulum)
    {
        $kelengkapan = $this->builder->kelengkapan($kurikulum);
        if (! $kelengkapan['lengkap']) {
            return $this->tolakBelumLengkap($kelengkapan);
        }

        return response()->json(['data' => $this->builder->build($kurikulum)]);
    }

    /** Ekspor Buku Kurikulum sebagai .docx (opsional narasi AI via ?naratif=1). */
    public function unduhDocx(Request $request, Kurikulum $kurikulum, BukuKurikulumDocxExporter $exporter, BukuKurikulumNaratifService $naratif)
    {
        $kelengkapan = $this->builder->kelengkapan($kurikulum);
        if (! $kelengkapan['lengkap']) {
            return $this->tolakBelumLengkap($kelengkapan);
        }

        $buku = $this->builder->build($kurikulum);

        $prosa = [];
        if ($request->boolean('naratif')) {
            // Fail-safe: bila AI gagal/tak tersedia, dokumen tetap dirakit tanpa narasi.
            $prosa = $naratif->generate($kurikulum, $buku);
        }

        $phpWord = $exporter->build($buku, $prosa);
        $writer = IOFactory::createWriter($phpWord, 'Word2007');

        $namaFile = sprintf(
            'Buku_Kurikulum_%s.docx',
            preg_replace('/[^A-Za-z0-9_-]+/', '', (string) ($kurikulum->kode ?: $kurikulum->tahun ?: $kurikulum->id)) ?: 'Kurikulum'
        );

        $tmp = tempnam(sys_get_temp_dir(), 'buku_docx_');
        $writer->save($tmp);

        return response()
            ->download($tmp, $namaFile, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ])
            ->deleteFileAfterSend(true);
    }

    private function tolakBelumLengkap(array $kelengkapan)
    {
        return response()->json([
            'message'    => 'Buku Kurikulum belum dapat dibuat: masih ada mata kuliah yang belum memiliki RPS.',
            'kelengkapan' => $kelengkapan,
        ], 422);
    }
}
