<?php

namespace Tests\Feature;

use App\Models\BahanKajian;
use App\Models\Cpl;
use App\Models\Cpmk;
use App\Models\CpmkCpl;
use App\Models\Institusi;
use App\Models\KomponenPenilaian;
use App\Models\KonfigurasiAturan;
use App\Models\Kurikulum;
use App\Models\MataKuliah;
use App\Models\MkBahanKajian;
use App\Models\Referensi;
use App\Models\Rubrik;
use App\Models\RpsMinggu;
use App\Models\RpsVersion;
use App\Models\SubCpmk;
use App\Models\User;
use App\Services\Rps\RpsObeContext;
use App\Services\Rps\RpsObeDocxExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Dokumen RPS format OBE (formulir mutu A–P): konteks turunan, cetak, DOCX, kelengkapan. */
class RpsObeFormatTest extends TestCase
{
    use RefreshDatabase;

    private Institusi $prodi;

    private function buatRps(string $status = 'draft'): RpsVersion
    {
        $this->prodi = Institusi::create(['kode' => 'PR-OBE', 'nama' => 'Farmasi', 'jenis' => 'prodi', 'jenjang' => 'Sarjana (S1)']);
        Sanctum::actingAs(User::factory()->create(['institusi_id' => $this->prodi->id]));
        $kur = Kurikulum::create(['institusi_id' => $this->prodi->id, 'kode' => 'KUR-OBE', 'nama' => 'Kur OBE', 'tahun' => '2024']);
        MataKuliah::create([
            'institusi_id' => $this->prodi->id,
            'kurikulum_id' => $kur->id,
            'kode_mk' => 'MK-OBE',
            'nama' => 'Farmakologi',
            'jenis_mk' => 'murni',
            'pola' => 'reguler',
            'sks_teori' => 2,
            'sks_praktik' => 1,
            'semester' => 3,
            'sifat' => 'wajib',
            'rumpun' => 'Farmakologi-Farmasi Klinik',
        ]);
        $cpl1 = Cpl::create(['institusi_id' => $this->prodi->id, 'kurikulum_id' => $kur->id, 'kode' => 'CPL-1', 'deskripsi' => 'CPL satu.']);
        $cpl2 = Cpl::create(['institusi_id' => $this->prodi->id, 'kurikulum_id' => $kur->id, 'kode' => 'CPL-2', 'deskripsi' => 'CPL dua.']);
        $c1 = Cpmk::create(['institusi_id' => $this->prodi->id, 'kode_mk' => 'MK-OBE', 'kode' => 'CPMK1', 'deskripsi' => 'Mahasiswa mampu menjelaskan.']);
        $c2 = Cpmk::create(['institusi_id' => $this->prodi->id, 'kode_mk' => 'MK-OBE', 'kode' => 'CPMK2', 'deskripsi' => 'Mahasiswa mampu menganalisis.']);
        CpmkCpl::create(['institusi_id' => $this->prodi->id, 'cpmk_id' => $c1->id, 'cpl_id' => $cpl1->id]);
        CpmkCpl::create(['institusi_id' => $this->prodi->id, 'cpmk_id' => $c2->id, 'cpl_id' => $cpl1->id]);
        CpmkCpl::create(['institusi_id' => $this->prodi->id, 'cpmk_id' => $c2->id, 'cpl_id' => $cpl2->id]);
        $s1 = SubCpmk::create(['institusi_id' => $this->prodi->id, 'cpmk_id' => $c1->id, 'kode' => 'Sub-CPMK1', 'deskripsi' => 'Sub satu.']);
        $s2 = SubCpmk::create(['institusi_id' => $this->prodi->id, 'cpmk_id' => $c2->id, 'kode' => 'Sub-CPMK2', 'deskripsi' => 'Sub dua.']);

        $bk = BahanKajian::create(['institusi_id' => $this->prodi->id, 'kurikulum_id' => $kur->id, 'nama' => 'Farmakokinetika']);
        MkBahanKajian::create(['institusi_id' => $this->prodi->id, 'kode_mk' => 'MK-OBE', 'bahan_kajian_id' => $bk->id]);
        Referensi::create(['institusi_id' => $this->prodi->id, 'kode_mk' => 'MK-OBE', 'tipe' => 'utama', 'sitasi' => 'Katzung (2021). Basic Pharmacology.']);
        Referensi::create(['institusi_id' => $this->prodi->id, 'kode_mk' => 'MK-OBE', 'tipe' => 'standar', 'sitasi' => 'Farmakope Indonesia VI.']);

        $rps = RpsVersion::create(['institusi_id' => $this->prodi->id, 'kode_mk' => 'MK-OBE', 'versi' => 2, 'status' => $status, 'bahasa' => 'id']);
        RpsMinggu::create([
            'rps_version_id' => $rps->id,
            'minggu_ke' => 1,
            'sub_cpmk_id' => $s1->id,
            'indikator' => 'Ketepatan menjelaskan ADME.',
            'teknik_kriteria_penilaian' => "Kriteria: ketepatan konsep.\nTeknik: tes tertulis.",
            'metode_pembelajaran' => 'Case Method',
            'bentuk_luring' => 'Kuliah tatap muka',
            'pengalaman_belajar' => 'Menganalisis kasus.',
            'media_sumber' => 'Slide, video ADME',
            'bukti_produk' => 'Peta konsep ADME',
            'materi_pustaka' => 'Farmakokinetika — ADME [Pustaka: 1]',
            'bobot_penilaian' => 10,
        ]);
        RpsMinggu::create([
            'rps_version_id' => $rps->id,
            'minggu_ke' => 2,
            'sub_cpmk_id' => null,
            'indikator' => 'Ketercapaian Sub-CPMK paruh pertama.',
            'teknik_kriteria_penilaian' => "Kriteria: ketepatan.\nTeknik: tes tertulis.",
            'materi_pustaka' => 'Evaluasi Tengah Semester (UTS)',
            'bobot_penilaian' => 30,
        ]);
        RpsMinggu::create([
            'rps_version_id' => $rps->id,
            'minggu_ke' => 3,
            'sub_cpmk_id' => $s2->id,
            'indikator' => 'Ketepatan analisis.',
            'materi_pustaka' => 'Farmakodinamika — reseptor',
        ]);
        $tugas = KomponenPenilaian::create(['rps_version_id' => $rps->id, 'sub_cpmk_id' => $s1->id, 'nama' => 'Tugas-1', 'jenis' => 'tugas', 'instrumen' => 'Rubrik laporan', 'bobot_persen' => 40, 'minggu_ke' => 1]);
        $rub = Rubrik::create(['komponen_penilaian_id' => $tugas->id, 'jenis' => 'analitik', 'jumlah_level_skala' => 4]);
        $rub->kriteria()->create(['kriteria' => 'Ketepatan analisis', 'bobot' => 100, 'urutan' => 1]);
        KomponenPenilaian::create(['rps_version_id' => $rps->id, 'sub_cpmk_id' => null, 'nama' => 'UTS', 'jenis' => 'uts', 'instrumen' => 'Soal uraian', 'bobot_persen' => 60, 'minggu_ke' => 2]);

        return $rps->fresh();
    }

    public function test_konteks_obe_menurunkan_seluruh_bagian(): void
    {
        $o = app(RpsObeContext::class)->build($this->buatRps());

        $this->assertSame('Draft', $o['kop']['status']);
        $this->assertSame(1, $o['kop']['revisi']);
        $this->assertSame('Sarjana (S1)', $o['identitas']['jenjang']);
        $this->assertSame('Kombinasi (Kuliah & Praktikum)', $o['identitas']['bentuk_pembelajaran']);
        $this->assertSame('Farmakologi-Farmasi Klinik', $o['identitas']['bidang_keahlian']);
        $this->assertSame('2024', $o['identitas']['tahun_kurikulum']);

        // CPL-1 didukung 2 CPMK → Utama; CPL-2 satu → Pendukung.
        $this->assertSame(['CPL-1' => 'Utama', 'CPL-2' => 'Pendukung'], collect($o['cpl'])->pluck('kontribusi', 'kode')->all());
        $this->assertSame(
            ['CPMK1' => ['CPL-1' => true, 'CPL-2' => false], 'CPMK2' => ['CPL-1' => true, 'CPL-2' => true]],
            collect($o['matriks_cpl_cpmk']['baris'])->pluck('cek', 'cpmk')->all()
        );
        $this->assertSame([['nama' => 'Farmakokinetika', 'cpmk' => ['CPMK1']]], $o['bahan_kajian']);

        $mg1 = $o['mingguan'][0];
        $this->assertSame('ketepatan konsep', $mg1['kriteria']);
        $this->assertSame('Peta konsep ADME', $mg1['bukti_produk']);
        $this->assertContains('tes tertulis.', $mg1['asesmen']);
        $mg2 = $o['mingguan'][1];
        $this->assertSame('UTS', $mg2['ujian']);
        $this->assertSame(['UTS – Soal uraian'], $mg2['asesmen']);
        // UTS tanpa Sub-CPMK: cakupan = CPMK pekan belajar sebelum UTS.
        $this->assertSame(['CPMK1'], $mg2['sub_cpmk']);
        $this->assertSame(['CPMK1'], $o['asesmen'][1]['cpmk']);

        $this->assertSame(['Tugas/Studi Kasus', 'UTS'], array_column($o['asesmen'], 'komponen'));
        $this->assertEquals(100, $o['total_bobot']);
        $this->assertSame('1–4', $o['kriteria'][0]['skala']);
        $this->assertSame('Kisi-kisi + soal', $o['kriteria'][1]['instrumen']);
        $this->assertSame('Rekap seluruh asesmen', end($o['evaluasi'])['komponen']);
        $this->assertSame(['Referensi Utama 1', 'Standar/Guideline/Farmakope'], array_column($o['referensi'], 'jenis'));
        $this->assertSame('85–100', $o['skala_nilai'][0]['rentang']);
        $this->assertSame('< 50', end($o['skala_nilai'])['rentang']);

        $lampiran = collect($o['lampiran'])->pluck('ada', 'key');
        $this->assertTrue($lampiran['kisi_kisi']);
        $this->assertTrue($lampiran['rubrik_tugas']);
        $this->assertFalse($lampiran['lkm']);
    }

    public function test_bahan_kajian_tanpa_sebutan_materi_memakai_peta_cpl(): void
    {
        $rps = $this->buatRps();
        $bk = BahanKajian::create(['institusi_id' => $this->prodi->id, 'nama' => 'Farmakologi Molekuler']);
        MkBahanKajian::create(['institusi_id' => $this->prodi->id, 'kode_mk' => 'MK-OBE', 'bahan_kajian_id' => $bk->id]);
        \App\Models\CplBahanKajian::create([
            'institusi_id' => $this->prodi->id,
            'cpl_id' => Cpl::where('kode', 'CPL-2')->value('id'),
            'bahan_kajian_id' => $bk->id,
        ]);

        $bkList = collect(app(RpsObeContext::class)->build($rps)['bahan_kajian'])->pluck('cpmk', 'nama');
        $this->assertSame(['CPMK2'], $bkList['Farmakologi Molekuler']);
    }

    public function test_upsert_skala_nilai_divalidasi(): void
    {
        $this->buatRps();
        $this->postJson('/api/v1/konfigurasi-aturan/upsert', [
            'jenis_aturan' => 'skala_nilai',
            'nilai' => ['rentang' => [['min' => 90, 'max' => 80, 'huruf' => 'A']]],
        ])->assertStatus(422)->assertJsonValidationErrors('nilai.rentang.0.max');

        $this->postJson('/api/v1/konfigurasi-aturan/upsert', [
            'jenis_aturan' => 'skala_nilai',
            'nilai' => ['rentang' => [['min' => 80, 'max' => 100, 'huruf' => 'A', 'keterangan' => 'Istimewa']]],
        ])->assertCreated();
    }

    public function test_skala_nilai_institusi_menimpa_bawaan(): void
    {
        $rps = $this->buatRps();
        KonfigurasiAturan::create(['institusi_id' => $this->prodi->id, 'jenis_aturan' => 'skala_nilai', 'nilai' => ['rentang' => [
            ['min' => 80, 'max' => 100, 'huruf' => 'A', 'keterangan' => 'Istimewa'],
            ['min' => 0, 'max' => 79.99, 'huruf' => 'B', 'keterangan' => null],
        ]]]);

        $skala = app(RpsObeContext::class)->build($rps)['skala_nilai'];
        $this->assertSame([['rentang' => '80–100', 'huruf' => 'A', 'keterangan' => 'Istimewa'], ['rentang' => '< 80', 'huruf' => 'B', 'keterangan' => null]], $skala);
    }

    public function test_simpan_kelengkapan_menimpa_turunan_dan_tampil_di_cetak(): void
    {
        $rps = $this->buatRps();

        $this->putJson("/api/v1/rps-versions/{$rps->id}/kelengkapan", [
            'kode_dokumen' => 'FM-RPS-01',
            'tanggal_penyusunan' => '2026-08-01',
            'tahun_akademik' => '2026/2027',
            'berlaku_mulai' => '2026-09-01',
            'kelengkapan' => [
                'otorisasi' => ['koordinator_bk' => ['nama' => 'Dr. Bidang', 'nidn' => '0011'], 'ketua_prodi' => ['nama' => '', 'nidn' => '']],
                'lms' => 'Kalam',
                'proporsi_luring' => 70,
                'peran_cpl' => ['CPL-2' => 'utama'],
                'lampiran' => ['lkm' => true, 'kisi_kisi' => false, 'asing' => true],
            ],
        ])->assertOk()->assertJsonPath('data.tahun_akademik', '2026/2027');

        $rps->refresh();
        $this->assertArrayNotHasKey('asing', $rps->kelengkapan['lampiran']);
        $this->assertArrayNotHasKey('ketua_prodi', $rps->kelengkapan['otorisasi']);

        $o = app(RpsObeContext::class)->build($rps);
        $this->assertSame('Dr. Bidang', $o['otorisasi'][1]['nama']);
        $this->assertSame(['CPL-1' => 'Utama', 'CPL-2' => 'Utama'], collect($o['cpl'])->pluck('kontribusi', 'kode')->all());
        $this->assertSame(30, $o['media']['proporsi_daring']);
        $lampiran = collect($o['lampiran'])->pluck('ada', 'key');
        $this->assertTrue($lampiran['lkm']);
        $this->assertFalse($lampiran['kisi_kisi']);

        $html = $this->get("/api/v1/rps-versions/{$rps->id}/cetak")->assertOk()->getContent();
        foreach (['FORMULIR MUTU', 'FM-RPS-01', '2026/2027', 'Dr. Bidang', 'Kalam', '70% luring; 30% daring', 'Peta konsep ADME', 'P. LAMPIRAN INSTRUMEN'] as $teks) {
            $this->assertStringContainsString($teks, $html);
        }
        $this->assertStringContainsString('KPT 2024', $this->get("/api/v1/rps-versions/{$rps->id}/cetak?format=kpt")->assertOk()->getContent());
    }

    public function test_kelengkapan_ditolak_saat_final_dan_format_tahun_divalidasi(): void
    {
        $rps = $this->buatRps();
        $this->putJson("/api/v1/rps-versions/{$rps->id}/kelengkapan", ['tahun_akademik' => '2026-ganjil'])
            ->assertStatus(422)->assertJsonValidationErrors('tahun_akademik');

        $rps->update(['status' => 'approved']);
        $this->putJson("/api/v1/rps-versions/{$rps->id}/kelengkapan", ['lms' => 'x'])->assertStatus(422);
    }

    public function test_docx_obe_dan_kpt_terbentuk(): void
    {
        $rps = $this->buatRps();
        $this->assertInstanceOf(\PhpOffice\PhpWord\PhpWord::class, app(RpsObeDocxExporter::class)->build($rps));

        $this->get("/api/v1/rps-versions/{$rps->id}/docx")->assertOk()->assertDownload('RPS_MK-OBE_v2.docx');
        $this->get("/api/v1/rps-versions/{$rps->id}/docx?format=kpt")->assertOk()->assertDownload('RPS_MK-OBE_v2_KPT.docx');
    }
}
