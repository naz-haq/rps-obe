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
use App\Services\Rps\RpsPrintContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RpsPrintContextTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Dokumen ekspor (PDF/DOCX) hanya memuat CPMK/Sub-CPMK yang BENAR-BENAR
     * dipakai versi ini (dirujuk rps_minggu). CPMK/Sub-CPMK sisa generate lama
     * (tak lagi tersemat) TIDAK ikut, dan isi versi lain tidak bocor — walau
     * cpmk/sub_cpmk berbagi tabel per kode_mk.
     */
    public function test_konteks_cetak_hanya_memuat_cpmk_sub_cpmk_versi_ini(): void
    {
        $prodi = Institusi::create(['kode' => 'PR-PC', 'nama' => 'Prodi PC', 'jenis' => 'prodi']);
        $kur = Kurikulum::create(['institusi_id' => $prodi->id, 'kode' => 'KUR-PC', 'nama' => 'Kur PC', 'tahun' => '2026']);
        MataKuliah::create([
            'institusi_id' => $prodi->id,
            'kurikulum_id' => $kur->id,
            'kode_mk' => 'MK-PC',
            'nama' => 'MK Print Context',
            'jenis_mk' => 'murni',
            'sks_teori' => 2,
            'sks_praktik' => 0,
            'semester' => 1,
        ]);
        Cpl::create(['institusi_id' => $prodi->id, 'kurikulum_id' => $kur->id, 'kode' => 'CPL-1', 'deskripsi' => 'Deskripsi CPL.']);

        // CPMK-DIPAKAI (tersemat di versi) dan CPMK-LAMA (sisa generate, dihapus dari versi).
        $cpmkDipakai = Cpmk::create(['institusi_id' => $prodi->id, 'kode_mk' => 'MK-PC', 'kode' => 'CPMK-DIPAKAI', 'deskripsi' => 'Dipakai.']);
        $cpmkLama = Cpmk::create(['institusi_id' => $prodi->id, 'kode_mk' => 'MK-PC', 'kode' => 'CPMK-LAMA', 'deskripsi' => 'Sisa generate lama.']);
        $subDipakai = SubCpmk::create(['institusi_id' => $prodi->id, 'cpmk_id' => $cpmkDipakai->id, 'kode' => 'Sub-CPMK-1', 'deskripsi' => 'Sub dipakai.']);
        SubCpmk::create(['institusi_id' => $prodi->id, 'cpmk_id' => $cpmkLama->id, 'kode' => 'Sub-CPMK-LAMA', 'deskripsi' => 'Sub sisa lama.']);

        $rps = RpsVersion::create(['institusi_id' => $prodi->id, 'kode_mk' => 'MK-PC', 'versi' => 2, 'status' => 'draft', 'bahasa' => 'id']);
        RpsMinggu::create(['rps_version_id' => $rps->id, 'minggu_ke' => 1, 'sub_cpmk_id' => $subDipakai->id]);
        RpsMinggu::create(['rps_version_id' => $rps->id, 'minggu_ke' => 8, 'sub_cpmk_id' => null]); // UTS

        $konteks = app(RpsPrintContext::class)->build($rps->fresh());

        $cpmkKodes = array_column($konteks['cpmk_list'], 'kode');
        $subKodes = array_column($konteks['sub_cpmk_list'], 'kode');

        $this->assertEqualsCanonicalizing(['CPMK-DIPAKAI'], $cpmkKodes);
        $this->assertEqualsCanonicalizing(['Sub-CPMK-1'], $subKodes);
        $this->assertNotContains('CPMK-LAMA', $cpmkKodes);
        $this->assertNotContains('Sub-CPMK-LAMA', $subKodes);
    }
}
