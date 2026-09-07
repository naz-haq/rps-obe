<?php

namespace Tests\Feature;

use App\Models\Cpmk;
use App\Models\Institusi;
use App\Models\Kurikulum;
use App\Models\MataKuliah;
use App\Models\RpsMinggu;
use App\Models\RpsVersion;
use App\Models\SubCpmk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Edit MANUAL rincian pertemuan per pekan (PUT /rps-versions/{id}/minggu/{ke}/rincian). */
class RincianManualTest extends TestCase
{
    use RefreshDatabase;

    private function buatRps(string $status = 'draft'): RpsVersion
    {
        $prodi = Institusi::create(['kode' => 'PR-RM', 'nama' => 'Prodi Rincian', 'jenis' => 'prodi']);
        Sanctum::actingAs(User::factory()->create(['institusi_id' => $prodi->id]));
        $kur = Kurikulum::create(['institusi_id' => $prodi->id, 'kode' => 'KUR-RM', 'nama' => 'Kur Rincian', 'tahun' => '2026']);
        MataKuliah::create([
            'institusi_id' => $prodi->id,
            'kurikulum_id' => $kur->id,
            'kode_mk' => 'MK-RM',
            'nama' => 'MK Rincian',
            'jenis_mk' => 'murni',
            'pola' => 'reguler',
            'sks_teori' => 2,
            'sks_praktik' => 0,
            'semester' => 1,
        ]);
        $cpmk = Cpmk::create(['institusi_id' => $prodi->id, 'kode_mk' => 'MK-RM', 'kode' => 'CPMK-1', 'deskripsi' => 'CPMK.']);
        $sub = SubCpmk::create(['institusi_id' => $prodi->id, 'cpmk_id' => $cpmk->id, 'kode' => 'Sub-CPMK-1', 'deskripsi' => 'Sub.']);

        $rps = RpsVersion::create(['institusi_id' => $prodi->id, 'kode_mk' => 'MK-RM', 'versi' => 1, 'status' => $status, 'bahasa' => 'id']);
        RpsMinggu::create([
            'rps_version_id' => $rps->id,
            'minggu_ke' => 1,
            'sub_cpmk_id' => $sub->id,
            'indikator' => 'Indikator pekan 1.',
            'materi_pustaka' => 'Materi pekan 1.',
        ]);

        return $rps->fresh();
    }

    public function test_simpan_skenario_manual(): void
    {
        $rps = $this->buatRps();

        $res = $this->putJson("/api/v1/rps-versions/{$rps->id}/minggu/1/rincian", [
            'rincian' => [[
                'topik' => 'Kontrak kuliah dan pengantar',
                'durasi_menit' => 100,
                'tahapan' => [
                    ['tahap' => 'Pendahuluan', 'kegiatan' => 'Dosen menyampaikan kontrak kuliah.', 'durasi_menit' => 12],
                    ['tahap' => 'Kegiatan Inti', 'kegiatan' => 'Diskusi peta konsep.', 'durasi_menit' => 78],
                    ['tahap' => 'Penutup', 'kegiatan' => 'Rangkuman dan refleksi.', 'durasi_menit' => 10],
                    ['tahap' => 'Kosong', 'kegiatan' => '   '], // dibuang
                ],
                'penugasan_terstruktur' => 'Membaca bab 1.',
                'belajar_mandiri' => 'Menyiapkan ringkasan.',
                'pt_menit' => 120,
                'bm_menit' => 120,
            ]],
        ]);

        $res->assertOk();
        $rincian = RpsMinggu::where('rps_version_id', $rps->id)->where('minggu_ke', 1)->first()->rincian_pertemuan;
        $this->assertCount(1, $rincian);
        $this->assertSame(1, $rincian[0]['pertemuan_ke']);
        $this->assertCount(3, $rincian[0]['tahapan']);
        $this->assertSame('Membaca bab 1.', $rincian[0]['penugasan_terstruktur']);
        $this->assertSame(120, $rincian[0]['pt_menit']);
    }

    public function test_simpan_pemecahan_manual_dan_hapus(): void
    {
        $rps = $this->buatRps();

        $res = $this->putJson("/api/v1/rps-versions/{$rps->id}/minggu/1/rincian", [
            'rincian' => [
                ['topik' => 'Sesi pagi', 'aktivitas' => 'Kuliah interaktif.', 'metode' => 'Ceramah', 'durasi_menit' => 50],
                ['topik' => '', 'aktivitas' => '  '], // entri kosong dibuang
                ['topik' => 'Sesi siang', 'aktivitas' => 'Diskusi kasus.', 'metode' => 'PBL', 'durasi_menit' => 50],
            ],
        ]);
        $res->assertOk();
        $rincian = RpsMinggu::where('rps_version_id', $rps->id)->where('minggu_ke', 1)->first()->rincian_pertemuan;
        $this->assertCount(2, $rincian);
        $this->assertSame([1, 2], array_column($rincian, 'pertemuan_ke'));
        $this->assertSame('Diskusi kasus.', $rincian[1]['aktivitas']);

        // Kirim kosong = hapus rincian pekan.
        $this->putJson("/api/v1/rps-versions/{$rps->id}/minggu/1/rincian", ['rincian' => []])->assertOk();
        $this->assertNull(RpsMinggu::where('rps_version_id', $rps->id)->where('minggu_ke', 1)->first()->rincian_pertemuan);
    }

    public function test_ditolak_bila_sudah_disetujui_dan_404_pekan_tak_ada(): void
    {
        $rps = $this->buatRps('approved');
        $this->putJson("/api/v1/rps-versions/{$rps->id}/minggu/1/rincian", ['rincian' => []])
            ->assertStatus(422);

        $draft = $this->buatRps2();
        $this->putJson("/api/v1/rps-versions/{$draft->id}/minggu/9/rincian", ['rincian' => []])
            ->assertStatus(404);
    }

    /** RPS kedua pada tenant yang sama untuk kasus 404. */
    private function buatRps2(): RpsVersion
    {
        $prodi = Institusi::where('kode', 'PR-RM')->firstOrFail();
        $rps = RpsVersion::create(['institusi_id' => $prodi->id, 'kode_mk' => 'MK-RM2', 'versi' => 1, 'status' => 'draft', 'bahasa' => 'id']);
        RpsMinggu::create(['rps_version_id' => $rps->id, 'minggu_ke' => 1, 'indikator' => 'x', 'materi_pustaka' => 'x']);

        return $rps->fresh();
    }
}
