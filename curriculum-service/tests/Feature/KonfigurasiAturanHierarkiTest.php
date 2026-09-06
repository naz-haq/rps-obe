<?php

namespace Tests\Feature;

use App\Models\Institusi;
use App\Models\KonfigurasiAturan;
use App\Models\User;
use App\Services\Rps\EstimasiWaktuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KonfigurasiAturanHierarkiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Reproduksi keluhan "aturan belum diatur padahal sudah": aturan diset di
     * tingkat UNIVERSITAS, MK ada di FAKULTAS (anak). Resolusi WAJIB mewaris ke
     * atas — MK fakultas memakai aturan universitas, bukan default.
     */
    public function test_estimasi_mewaris_konversi_sks_dari_institusi_induk(): void
    {
        $univ = Institusi::create(['kode' => 'UNIV', 'nama' => 'Universitas', 'jenis' => 'universitas']);
        $fakultas = Institusi::create(['kode' => 'FAK', 'nama' => 'Fakultas', 'jenis' => 'fakultas', 'parent_id' => $univ->id]);

        KonfigurasiAturan::create([
            'institusi_id' => $univ->id,
            'jenis_aturan' => 'konversi_sks',
            'nilai' => ['teori_tatap_muka' => 55, 'teori_terstruktur' => 65, 'teori_mandiri' => 65, 'praktik' => 175],
        ]);

        $konv = app(EstimasiWaktuService::class)->konversiUntuk($fakultas->id);

        // Nilai universitas (bukan default SN-Dikti 50/60/60/170).
        $this->assertSame(55, $konv['teori_tatap_muka']);
        $this->assertSame(175, $konv['praktik']);
    }

    /** Endpoint efektif=1 melaporkan aturan yang diwarisi dari induk (untuk banner). */
    public function test_endpoint_efektif_melaporkan_aturan_warisan(): void
    {
        $univ = Institusi::create(['kode' => 'UNIV2', 'nama' => 'Universitas 2', 'jenis' => 'universitas']);
        $fakultas = Institusi::create(['kode' => 'FAK2', 'nama' => 'Fakultas 2', 'jenis' => 'fakultas', 'parent_id' => $univ->id]);
        KonfigurasiAturan::create(['institusi_id' => $univ->id, 'jenis_aturan' => 'jumlah_minggu', 'nilai' => ['minggu_efektif' => 16]]);
        KonfigurasiAturan::create(['institusi_id' => $fakultas->id, 'jenis_aturan' => 'durasi_sesi', 'nilai' => ['menit' => 50]]);

        Sanctum::actingAs(User::factory()->create(['institusi_id' => $fakultas->id]));

        $res = $this->getJson("/api/v1/konfigurasi-aturan?institusi_id={$fakultas->id}&efektif=1")->assertOk();
        $jenis = collect($res->json('data'))->pluck('jenis_aturan')->all();

        $this->assertContains('jumlah_minggu', $jenis); // diwarisi dari universitas
        $this->assertContains('durasi_sesi', $jenis);   // milik fakultas sendiri
    }
}
