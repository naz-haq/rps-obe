<?php

namespace Tests\Feature;

use App\Models\Dosen;
use App\Models\Institusi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Kandidat pengampu diambil dari akun pengguna ber-NIDN + master dosen. */
class PengampuKandidatTest extends TestCase
{
    use RefreshDatabase;

    public function test_kandidat_gabungan_akun_dan_master_lintas_hierarki(): void
    {
        $fakultas = Institusi::create(['kode' => 'FK-PG', 'nama' => 'Fakultas Pengampu', 'jenis' => 'fakultas']);
        $prodi = Institusi::create(['kode' => 'PR-PG', 'nama' => 'Prodi Pengampu', 'jenis' => 'prodi', 'parent_id' => $fakultas->id]);

        Sanctum::actingAs(User::factory()->create(['institusi_id' => $prodi->id]));

        User::factory()->create(['institusi_id' => $prodi->id, 'name' => 'Budi Santoso', 'nidn' => '0011', 'jabatan' => 'Lektor', 'is_active' => true]);
        User::factory()->create(['institusi_id' => $fakultas->id, 'name' => 'Ani Lestari', 'nidn' => '0022', 'is_active' => true]);
        User::factory()->create(['institusi_id' => $prodi->id, 'name' => 'Nonaktif', 'nidn' => '0033', 'is_active' => false]);
        User::factory()->create(['institusi_id' => $prodi->id, 'name' => 'Tanpa NIDN', 'nidn' => null]);
        Dosen::create(['institusi_id' => $prodi->id, 'nidn' => '0044', 'nama' => 'Citra Dewi']);

        $res = $this->getJson("/api/v1/pengampu/kandidat?institusi_id={$prodi->id}");

        $res->assertOk();
        $nidn = array_column($res->json('data'), 'nidn');
        sort($nidn);
        $this->assertSame(['0011', '0022', '0044'], $nidn);

        $akun = collect($res->json('data'))->firstWhere('nidn', '0011');
        $this->assertSame('Budi Santoso', $akun['nama']);
        $this->assertSame('Lektor', $akun['jabatan']);
        $this->assertSame('akun', $akun['sumber']);
        $this->assertSame('master', collect($res->json('data'))->firstWhere('nidn', '0044')['sumber']);
    }

    public function test_kandidat_bisa_dicari(): void
    {
        $prodi = Institusi::create(['kode' => 'PR-PG2', 'nama' => 'Prodi Cari', 'jenis' => 'prodi']);
        Sanctum::actingAs(User::factory()->create(['institusi_id' => $prodi->id]));
        User::factory()->create(['institusi_id' => $prodi->id, 'name' => 'Budi Santoso', 'nidn' => '0011', 'is_active' => true]);
        User::factory()->create(['institusi_id' => $prodi->id, 'name' => 'Ani Lestari', 'nidn' => '0022', 'is_active' => true]);

        $res = $this->getJson("/api/v1/pengampu/kandidat?institusi_id={$prodi->id}&q=ani");

        $res->assertOk();
        $this->assertSame(['0022'], array_column($res->json('data'), 'nidn'));
    }
}
