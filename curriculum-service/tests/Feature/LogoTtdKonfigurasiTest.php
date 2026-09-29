<?php

namespace Tests\Feature;

use App\Models\Institusi;
use App\Models\KonfigurasiAturan;
use App\Models\MkPengampu;
use App\Models\RpsVersion;
use App\Models\User;
use App\Services\Rps\RpsObeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Logo kop, spesimen TTD elektronik, dan konfigurasi aturan oleh akun lintas institusi. */
class LogoTtdKonfigurasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function png(string $nama = 'gambar.png'): UploadedFile
    {
        return UploadedFile::fake()->image($nama, 120, 60);
    }

    public function test_logo_diunggah_dan_diwariskan_ke_prodi(): void
    {
        $univ = Institusi::create(['kode' => 'U1', 'nama' => 'Universitas Uji', 'jenis' => 'universitas']);
        $prodi = Institusi::create(['kode' => 'P1', 'nama' => 'Prodi Uji', 'jenis' => 'prodi', 'parent_id' => $univ->id]);
        $admin = User::factory()->create(['institusi_id' => $univ->id]);
        $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'prodi.manage', 'guard_name' => 'web']));
        Sanctum::actingAs($admin);

        $this->post("/api/v1/institusi/{$univ->id}/logo", ['file' => UploadedFile::fake()->create('x.svg', 5, 'image/svg+xml')], ['Accept' => 'application/json'])
            ->assertStatus(422);
        $this->post("/api/v1/institusi/{$univ->id}/logo", ['file' => $this->png()], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.logo_versi', fn($v) => is_string($v));

        $path = $univ->fresh()->logo_path;
        $this->assertStringStartsWith("institusi/{$univ->id}/", $path);
        Storage::disk('local')->assertExists($path);
        $this->get("/api/v1/institusi/{$univ->id}/logo")->assertOk();

        $rps = RpsVersion::create(['institusi_id' => $prodi->id, 'kode_mk' => 'MK-L', 'versi' => 1, 'status' => 'draft', 'bahasa' => 'id']);
        $ctx = app(RpsObeContext::class);
        $this->assertSame(Storage::disk('local')->path($path), $ctx->build($rps, true)['kop']['logo_file']);
        $this->assertNull($ctx->build($rps)['kop']['logo_file']);

        $this->deleteJson("/api/v1/institusi/{$univ->id}/logo")->assertOk();
        Storage::disk('local')->assertMissing($path);
    }

    public function test_logo_ditolak_tanpa_izin_kelola(): void
    {
        $univ = Institusi::create(['kode' => 'U2', 'nama' => 'Univ', 'jenis' => 'universitas']);
        Sanctum::actingAs(User::factory()->create(['institusi_id' => $univ->id]));

        $this->post("/api/v1/institusi/{$univ->id}/logo", ['file' => $this->png()], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_ttd_dibubuhkan_hanya_setelah_tahapnya_selesai(): void
    {
        $prodi = Institusi::create(['kode' => 'P3', 'nama' => 'Prodi TTD', 'jenis' => 'prodi']);
        $koor = User::factory()->create(['institusi_id' => $prodi->id, 'nidn' => '0101']);
        $kaprodi = User::factory()->create(['institusi_id' => $prodi->id, 'nidn' => '0202']);
        MkPengampu::create(['institusi_id' => $prodi->id, 'kode_mk' => 'MK-T', 'dosen_nidn' => '0101', 'peran' => 'koordinator']);

        foreach ([$koor, $kaprodi] as $u) {
            Sanctum::actingAs($u);
            $this->post('/api/v1/auth/profile/ttd', ['file' => $this->png('ttd.png')], ['Accept' => 'application/json'])
                ->assertOk()->assertJsonPath('data.ttd_versi', fn($v) => is_string($v));
            $this->assertArrayNotHasKey('ttd_path', $u->fresh()->toArray());
        }
        $this->get('/api/v1/auth/profile/ttd')->assertOk();

        $rps = RpsVersion::create(['institusi_id' => $prodi->id, 'kode_mk' => 'MK-T', 'versi' => 1, 'status' => 'draft', 'bahasa' => 'id']);
        $ctx = app(RpsObeContext::class);
        $this->assertSame([null, null, null], array_column($ctx->build($rps)['otorisasi'], 'ttd'));

        $rps->update(['status' => 'review', 'submitted_at' => now()]);
        $o = $ctx->build($rps->fresh(), true)['otorisasi'];
        $this->assertNotNull($o[0]['ttd']);
        $this->assertFileExists($o[0]['ttd']['file']);
        $this->assertNull($o[2]['ttd']);

        $rps->update(['status' => 'approved', 'approved_by' => $kaprodi->id, 'approved_at' => now()]);
        $o = $ctx->build($rps->fresh())['otorisasi'];
        $this->assertNotNull($o[2]['ttd']);
        $this->assertArrayNotHasKey('file', $o[2]['ttd']);
        $this->assertMatchesRegularExpression('/^[0-9A-F]{12}$/', $o[2]['ttd']['kode']);
        // Koordinator BK tanpa NIDN tercantum tidak bertanda tangan.
        $this->assertNull($o[1]['ttd']);

        // Nama manual tanpa NIDN tidak boleh memakai TTD penyetuju.
        $rps->update(['kelengkapan' => ['otorisasi' => ['ketua_prodi' => ['nama' => 'Dr. Lain']]]]);
        $o = $ctx->build($rps->fresh())['otorisasi'];
        $this->assertSame('Dr. Lain', $o[2]['nama']);
        $this->assertNull($o[2]['ttd']);

        $html = $this->get("/api/v1/rps-versions/{$rps->id}/cetak")->assertOk()->getContent();
        $this->assertStringContainsString('Ditandatangani elektronik', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->get("/api/v1/rps-versions/{$rps->id}/docx")->assertOk();
    }

    public function test_superadmin_tanpa_institusi_menyimpan_aturan_ke_unit_terpilih(): void
    {
        $univ = Institusi::create(['kode' => 'U4', 'nama' => 'Univ Aturan', 'jenis' => 'universitas']);
        $prodi = Institusi::create(['kode' => 'P4', 'nama' => 'Prodi Aturan', 'jenis' => 'prodi', 'parent_id' => $univ->id]);
        Sanctum::actingAs(User::factory()->create(['institusi_id' => null]));

        $this->postJson('/api/v1/konfigurasi-aturan/upsert', ['jenis_aturan' => 'durasi_sesi', 'nilai' => ['menit_per_sesi' => 50]])
            ->assertStatus(422)->assertJsonValidationErrors('institusi_id');
        $this->postJson('/api/v1/konfigurasi-aturan/upsert', ['institusi_id' => $univ->id, 'jenis_aturan' => 'durasi_sesi', 'nilai' => ['menit_per_sesi' => 45]])
            ->assertCreated();

        $this->getJson('/api/v1/konfigurasi-aturan')->assertOk()->assertJsonPath('data.0.institusi_id', $univ->id);
        $this->getJson("/api/v1/konfigurasi-aturan?institusi_id={$prodi->id}&efektif=1")
            ->assertOk()->assertJsonPath('data.0.nilai.menit_per_sesi', 45);
    }

    public function test_akun_prodi_hanya_melihat_aturan_institusinya(): void
    {
        $a = Institusi::create(['kode' => 'A5', 'nama' => 'Prodi A', 'jenis' => 'prodi']);
        $b = Institusi::create(['kode' => 'B5', 'nama' => 'Prodi B', 'jenis' => 'prodi']);
        KonfigurasiAturan::create(['institusi_id' => $a->id, 'jenis_aturan' => 'durasi_sesi', 'nilai' => ['menit_per_sesi' => 40]]);
        Sanctum::actingAs(User::factory()->create(['institusi_id' => $b->id]));

        $this->getJson('/api/v1/konfigurasi-aturan')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/konfigurasi-aturan?institusi_id={$a->id}")->assertForbidden();
    }
}
