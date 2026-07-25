<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BukuKurikulumNaratif;
use App\Models\Kurikulum;
use App\Services\Kurikulum\BukuKurikulumBuilder;
use App\Services\Kurikulum\BukuKurikulumDocxExporter;
use App\Services\Kurikulum\BukuKurikulumNaratifService;
use PhpOffice\PhpWord\IOFactory;

/**
 * Modul Buku Kurikulum: rakit dokumen kurikulum prodi (deterministik) + narasi
 * AI yang dapat dipratinjau/di-regenerate, lalu ekspor .docx. Diblokir bila ada
 * Mata Kuliah yang belum memiliki RPS ter-commit.
 */
class KurikulumBukuController extends Controller
{
    public function __construct(private BukuKurikulumBuilder $builder) {}

    /** Status kelengkapan prasyarat (semua MK wajib punya RPS). */
    public function kelengkapan(Kurikulum $kurikulum)
    {
        return response()->json(['data' => $this->builder->kelengkapan($kurikulum)]);
    }

    /** Pratinjau: struktur deterministik + narasi tersimpan (hormati blokir). */
    public function pratinjau(Kurikulum $kurikulum)
    {
        $kelengkapan = $this->builder->kelengkapan($kurikulum);
        if (! $kelengkapan['lengkap']) {
            return $this->tolakBelumLengkap($kelengkapan);
        }

        $tersimpan = BukuKurikulumNaratif::where('kurikulum_id', $kurikulum->id)->first();

        return response()->json([
            'data'         => $this->builder->build($kurikulum),
            'naratif'      => $tersimpan?->naratif ?? [],
            'naratif_pada' => optional($tersimpan?->digenerate_pada)->toIso8601String(),
        ]);
    }

    /** Generate / regenerate narasi AI lalu SIMPAN (untuk ditinjau & diunduh). */
    public function generateNaratif(Kurikulum $kurikulum, BukuKurikulumNaratifService $naratif)
    {
        $kelengkapan = $this->builder->kelengkapan($kurikulum);
        if (! $kelengkapan['lengkap']) {
            return $this->tolakBelumLengkap($kelengkapan);
        }

        $buku = $this->builder->build($kurikulum);
        $prosa = $naratif->generate($kurikulum, $buku);

        $row = BukuKurikulumNaratif::updateOrCreate(
            ['kurikulum_id' => $kurikulum->id],
            ['naratif' => $prosa, 'digenerate_pada' => now()],
        );

        return response()->json([
            'naratif'      => $prosa,
            'kosong'       => $prosa === [],
            'naratif_pada' => optional($row->digenerate_pada)->toIso8601String(),
            'message'      => $prosa === []
                ? 'Narasi AI belum tersedia (layanan AI gagal/kosong). Dokumen tetap dapat diunduh tanpa narasi.'
                : 'Narasi berhasil dibuat.',
        ]);
    }

    /** Ekspor .docx memakai narasi tersimpan yang sudah ditinjau. */
    public function unduhDocx(Kurikulum $kurikulum, BukuKurikulumDocxExporter $exporter)
    {
        $kelengkapan = $this->builder->kelengkapan($kurikulum);
        if (! $kelengkapan['lengkap']) {
            return $this->tolakBelumLengkap($kelengkapan);
        }

        $buku = $this->builder->build($kurikulum);
        $prosa = BukuKurikulumNaratif::where('kurikulum_id', $kurikulum->id)->value('naratif');
        $prosa = is_array($prosa) ? $prosa : [];

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
