<?php

namespace Tests\Feature;

use App\Models\Cpl;
use App\Models\Cpmk;
use App\Models\Institusi;
use App\Models\Kurikulum;
use App\Models\MataKuliah;
use App\Models\RpsMinggu;
use App\Models\RpsVersion;
use App\Models\SubCpmk;
use App\Services\Rps\RpsDocxExporter;
use App\Services\Rps\RpsPrintContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RpsExportContentTest extends TestCase
{
    use RefreshDatabase;

    private function buatRpsDenganPenugasan(): RpsVersion
    {
        $prodi = Institusi::create(['kode' => 'PR-EX', 'nama' => 'Prodi Ekspor', 'jenis' => 'prodi']);
        $kur = Kurikulum::create(['institusi_id' => $prodi->id, 'kode' => 'KUR-EX', 'nama' => 'Kur Ekspor', 'tahun' => '2026']);
        MataKuliah::create([
            'institusi_id' => $prodi->id,
            'kurikulum_id' => $kur->id,
            'kode_mk' => 'MK-EX',
            'nama' => 'MK Ekspor',
            'jenis_mk' => 'murni',
            'pola' => 'blok',
            'sks_teori' => 2,
            'sks_praktik' => 0,
            'semester' => 1,
        ]);
        Cpl::create(['institusi_id' => $prodi->id, 'kurikulum_id' => $kur->id, 'kode' => 'CPL-1', 'deskripsi' => 'CPL.']);
        $cpmk = Cpmk::create(['institusi_id' => $prodi->id, 'kode_mk' => 'MK-EX', 'kode' => 'CPMK-1', 'deskripsi' => 'CPMK.']);
        $sub = SubCpmk::create(['institusi_id' => $prodi->id, 'cpmk_id' => $cpmk->id, 'kode' => 'Sub-CPMK-1', 'deskripsi' => 'Sub.']);

        $rps = RpsVersion::create(['institusi_id' => $prodi->id, 'kode_mk' => 'MK-EX', 'versi' => 1, 'status' => 'draft', 'bahasa' => 'id']);
        RpsMinggu::create([
            'rps_version_id' => $rps->id,
            'minggu_ke' => 1,
            'sub_cpmk_id' => $sub->id,
            'indikator' => 'Indikator pekan 1.',
            'materi_pustaka' => 'Materi pekan 1.',
            'pengalaman_belajar' => 'Mahasiswa menyusun laporan analisis kasus obat.',
            'rincian_pertemuan' => [
                ['pertemuan_ke' => 1, 'topik' => 'Pengantar farmakologi', 'aktivitas' => 'Diskusi kasus pemicu', 'metode' => 'PBL', 'durasi_menit' => 100],
                ['pertemuan_ke' => 2, 'topik' => 'Mekanisme reseptor', 'aktivitas' => 'Latihan analisis', 'metode' => 'Diskusi', 'durasi_menit' => 100],
            ],
        ]);

        return $rps->fresh();
    }

    public function test_pdf_memuat_penugasan_dan_rincian_pertemuan(): void
    {
        $rps = $this->buatRpsDenganPenugasan();
        $rps->load(['minggu.subCpmk.cpmk', 'komponenPenilaian.subCpmk.cpmk', 'komponenPenilaian.rubrik.kriteria']);
        $mk = MataKuliah::where('kode_mk', $rps->kode_mk)->first();
        $institusi = Institusi::find($rps->institusi_id);
        $konteks = app(RpsPrintContext::class)->build($rps);

        $html = view('rps.cetak', [
            'rps' => $rps,
            'mk' => $mk,
            'institusi' => $institusi,
            'minggu' => $rps->minggu->sortBy('minggu_ke')->values(),
            'komponen' => $rps->komponenPenilaian->values(),
            'cplDiampu' => collect(),
            'konteks' => $konteks,
        ])->render();

        $this->assertStringContainsString('Mahasiswa menyusun laporan analisis kasus obat.', $html);
        $this->assertStringContainsString('Rincian Pertemuan', $html);
        $this->assertStringContainsString('Pengantar farmakologi', $html);
        $this->assertStringContainsString('Diskusi kasus pemicu', $html);
    }

    public function test_docx_terbentuk_tanpa_galat_dengan_rincian_pertemuan(): void
    {
        $rps = $this->buatRpsDenganPenugasan();
        $phpWord = app(RpsDocxExporter::class)->build($rps);
        $this->assertInstanceOf(\PhpOffice\PhpWord\PhpWord::class, $phpWord);
    }

    /** MK reguler: rincian_pertemuan berisi skenario tahapan + PT/BM. */
    private function buatRpsSkenarioReguler(): RpsVersion
    {
        $prodi = Institusi::create(['kode' => 'PR-SK', 'nama' => 'Prodi Skenario', 'jenis' => 'prodi']);
        $kur = Kurikulum::create(['institusi_id' => $prodi->id, 'kode' => 'KUR-SK', 'nama' => 'Kur Skenario', 'tahun' => '2026']);
        MataKuliah::create([
            'institusi_id' => $prodi->id,
            'kurikulum_id' => $kur->id,
            'kode_mk' => 'MK-SK',
            'nama' => 'MK Skenario',
            'jenis_mk' => 'murni',
            'pola' => 'reguler',
            'sks_teori' => 2,
            'sks_praktik' => 0,
            'semester' => 1,
        ]);
        $cpmk = Cpmk::create(['institusi_id' => $prodi->id, 'kode_mk' => 'MK-SK', 'kode' => 'CPMK-1', 'deskripsi' => 'CPMK.']);
        $sub = SubCpmk::create(['institusi_id' => $prodi->id, 'cpmk_id' => $cpmk->id, 'kode' => 'Sub-CPMK-1', 'deskripsi' => 'Sub.']);

        $rps = RpsVersion::create(['institusi_id' => $prodi->id, 'kode_mk' => 'MK-SK', 'versi' => 1, 'status' => 'draft', 'bahasa' => 'id']);
        RpsMinggu::create([
            'rps_version_id' => $rps->id,
            'minggu_ke' => 1,
            'sub_cpmk_id' => $sub->id,
            'indikator' => 'Indikator pekan 1.',
            'materi_pustaka' => 'Materi pekan 1.',
            'rincian_pertemuan' => [[
                'pertemuan_ke' => 1,
                'topik' => 'Konsep dasar farmakokinetika',
                'durasi_menit' => 100,
                'tahapan' => [
                    ['tahap' => 'Pendahuluan', 'kegiatan' => 'Apersepsi kaitan materi pekan lalu.', 'durasi_menit' => 12],
                    ['tahap' => 'Inti — Diskusi kelompok', 'kegiatan' => 'Analisis kasus dosis obat.', 'durasi_menit' => 78],
                    ['tahap' => 'Penutup', 'kegiatan' => 'Rangkuman dan refleksi.', 'durasi_menit' => 10],
                ],
                'penugasan_terstruktur' => 'Menyusun ringkasan parameter farmakokinetika.',
                'belajar_mandiri' => 'Membaca bab 2 [Pustaka: 1].',
                'pt_menit' => 120,
                'bm_menit' => 120,
            ]],
        ]);

        return $rps->fresh();
    }

    public function test_pdf_memuat_skenario_tahapan_reguler(): void
    {
        $rps = $this->buatRpsSkenarioReguler();
        $rps->load(['minggu.subCpmk.cpmk', 'komponenPenilaian.subCpmk.cpmk', 'komponenPenilaian.rubrik.kriteria']);
        $mk = MataKuliah::where('kode_mk', $rps->kode_mk)->first();
        $institusi = Institusi::find($rps->institusi_id);
        $konteks = app(RpsPrintContext::class)->build($rps);

        $html = view('rps.cetak', [
            'rps' => $rps,
            'mk' => $mk,
            'institusi' => $institusi,
            'minggu' => $rps->minggu->sortBy('minggu_ke')->values(),
            'komponen' => $rps->komponenPenilaian->values(),
            'cplDiampu' => collect(),
            'konteks' => $konteks,
        ])->render();

        $this->assertStringContainsString('Skenario Pertemuan', $html);
        $this->assertStringContainsString('Pendahuluan', $html);
        $this->assertStringContainsString('Analisis kasus dosis obat.', $html);
        $this->assertStringContainsString('Penugasan Terstruktur', $html);
        $this->assertStringContainsString('Membaca bab 2 [Pustaka: 1].', $html);
    }

    public function test_docx_terbentuk_tanpa_galat_dengan_skenario_reguler(): void
    {
        $rps = $this->buatRpsSkenarioReguler();
        $phpWord = app(RpsDocxExporter::class)->build($rps);
        $this->assertInstanceOf(\PhpOffice\PhpWord\PhpWord::class, $phpWord);
    }
}
