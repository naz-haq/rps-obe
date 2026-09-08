<?php

namespace Tests\Feature;

use App\Models\Cpmk;
use App\Models\Institusi;
use App\Models\Kurikulum;
use App\Models\MataKuliah;
use App\Models\RpsMinggu;
use App\Models\RpsVersion;
use App\Models\SubCpmk;
use App\Services\Rps\RpsPrintContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Satu pekan boleh menyasar beberapa Sub-CPMK (utama + tambahan). */
class MingguMultiSubCpmkTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:RpsVersion,1:SubCpmk,2:SubCpmk} */
    private function buatRps(): array
    {
        $prodi = Institusi::create(['kode' => 'PR-MS', 'nama' => 'Prodi Multi', 'jenis' => 'prodi']);
        $kur = Kurikulum::create(['institusi_id' => $prodi->id, 'kode' => 'KUR-MS', 'nama' => 'Kur Multi', 'tahun' => '2026']);
        MataKuliah::create([
            'institusi_id' => $prodi->id,
            'kurikulum_id' => $kur->id,
            'kode_mk' => 'MK-MS',
            'nama' => 'MK Multi',
            'jenis_mk' => 'murni',
            'pola' => 'reguler',
            'sks_teori' => 2,
            'sks_praktik' => 0,
            'semester' => 1,
        ]);
        $cpmk = Cpmk::create(['institusi_id' => $prodi->id, 'kode_mk' => 'MK-MS', 'kode' => 'CPMK1', 'deskripsi' => 'CPMK.']);
        $s1 = SubCpmk::create(['institusi_id' => $prodi->id, 'cpmk_id' => $cpmk->id, 'kode' => 'Sub-CPMK-1', 'deskripsi' => 'Sub satu.']);
        $s2 = SubCpmk::create(['institusi_id' => $prodi->id, 'cpmk_id' => $cpmk->id, 'kode' => 'Sub-CPMK-2', 'deskripsi' => 'Sub dua.']);

        $rps = RpsVersion::create(['institusi_id' => $prodi->id, 'kode_mk' => 'MK-MS', 'versi' => 1, 'status' => 'draft', 'bahasa' => 'id']);

        return [$rps, $s1, $s2];
    }

    public function test_pekan_ujian_boleh_menyasar_beberapa_sub_cpmk(): void
    {
        [$rps, $s1, $s2] = $this->buatRps();

        $minggu = RpsMinggu::create([
            'rps_version_id' => $rps->id,
            'minggu_ke' => 8,
            'sub_cpmk_id' => $s1->id,
            'indikator' => 'Ketercapaian paruh pertama.',
            'materi_pustaka' => 'Evaluasi Tengah Semester (UTS)',
        ]);
        $minggu->subCpmkSemua()->sync([$s1->id => ['urutan' => 0], $s2->id => ['urutan' => 1]]);

        $this->assertSame(['Sub-CPMK-1', 'Sub-CPMK-2'], $minggu->subCpmkSemua()->pluck('kode')->all());

        $ctx = app(RpsPrintContext::class);
        $minggu->load('subCpmkSemua');
        $this->assertSame(['Sub-CPMK-2'], $ctx->subCpmkTambahan($minggu)->pluck('kode')->all());
        $this->assertSame([$s1->id, $s2->id], $ctx->subIdsMinggu($minggu));
    }

    public function test_api_detail_dan_pdf_memuat_sub_cpmk_tambahan(): void
    {
        [$rps, $s1, $s2] = $this->buatRps();
        $minggu = RpsMinggu::create([
            'rps_version_id' => $rps->id,
            'minggu_ke' => 8,
            'sub_cpmk_id' => $s1->id,
            'indikator' => 'Ketercapaian paruh pertama.',
            'materi_pustaka' => 'Materi pekan 8.',
        ]);
        $minggu->subCpmkSemua()->sync([$s1->id => ['urutan' => 0], $s2->id => ['urutan' => 1]]);

        $rps->load(['minggu.subCpmk.cpmk', 'minggu.subCpmkSemua', 'komponenPenilaian.subCpmk.cpmk', 'komponenPenilaian.rubrik.kriteria']);
        $html = view('rps.cetak', [
            'rps' => $rps,
            'mk' => MataKuliah::where('kode_mk', 'MK-MS')->first(),
            'institusi' => Institusi::find($rps->institusi_id),
            'minggu' => $rps->minggu->sortBy('minggu_ke')->values(),
            'komponen' => collect(),
            'cplDiampu' => collect(),
            'konteks' => app(RpsPrintContext::class)->build($rps),
        ])->render();

        $this->assertStringContainsString('Sub-CPMK-2', $html);
        $this->assertStringContainsString('Sub dua.', $html);
    }
}
