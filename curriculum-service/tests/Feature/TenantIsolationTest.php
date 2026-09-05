<?php

namespace Tests\Feature;

use App\Models\GenerateSession;
use App\Models\Institusi;
use App\Models\MataKuliah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_user_only_sees_sessions_from_their_institution(): void
    {
        [$tenantA, $tenantB] = $this->institutions();
        $ownSession = GenerateSession::create(['institusi_id' => $tenantA->id]);
        GenerateSession::create(['institusi_id' => $tenantB->id]);
        Sanctum::actingAs(User::factory()->create(['institusi_id' => $tenantA->id]));

        $this->getJson('/api/v1/generate-sessions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownSession->id);
    }

    public function test_tenant_user_cannot_select_another_institution(): void
    {
        [$tenantA, $tenantB] = $this->institutions();
        Sanctum::actingAs(User::factory()->create(['institusi_id' => $tenantA->id]));

        $this->getJson("/api/v1/generate-sessions?institusi_id={$tenantB->id}")
            ->assertForbidden();
    }

    public function test_tenant_user_cannot_access_another_institutions_route_model(): void
    {
        [$tenantA, $tenantB] = $this->institutions();
        $foreignSession = GenerateSession::create(['institusi_id' => $tenantB->id]);
        Sanctum::actingAs(User::factory()->create(['institusi_id' => $tenantA->id]));

        $this->getJson("/api/v1/generate-sessions/{$foreignSession->id}")
            ->assertForbidden();
    }

    public function test_tenant_user_cannot_start_a_session_for_another_institutions_course(): void
    {
        [$tenantA, $tenantB] = $this->institutions();
        $foreignCourse = MataKuliah::create([
            'institusi_id' => $tenantB->id,
            'kode_mk' => 'MK-B',
            'nama' => 'Mata Kuliah Tenant B',
        ]);
        Sanctum::actingAs(User::factory()->create(['institusi_id' => $tenantA->id]));

        $this->postJson('/api/v1/generate-sessions', ['mk_id' => $foreignCourse->id])
            ->assertForbidden();
    }

    public function test_global_administrator_can_select_an_institution(): void
    {
        [, $tenantB] = $this->institutions();
        $foreignSession = GenerateSession::create(['institusi_id' => $tenantB->id]);
        Sanctum::actingAs(User::factory()->create(['institusi_id' => null]));

        $this->getJson("/api/v1/generate-sessions?institusi_id={$tenantB->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $foreignSession->id);
    }

    public function test_tenant_user_only_sees_their_institution_in_lookup(): void
    {
        [$tenantA] = $this->institutions();
        Sanctum::actingAs(User::factory()->create(['institusi_id' => $tenantA->id]));

        $this->getJson('/api/v1/institusi')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $tenantA->id);
    }

    public function test_global_administrator_sees_all_institutions_in_lookup(): void
    {
        $this->institutions();
        Sanctum::actingAs(User::factory()->create(['institusi_id' => null]));

        $this->getJson('/api/v1/institusi')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /** @return array{Institusi, Institusi} */
    private function institutions(): array
    {
        return [
            Institusi::create(['kode' => 'TENANT-A', 'nama' => 'Tenant A', 'jenis' => 'prodi']),
            Institusi::create(['kode' => 'TENANT-B', 'nama' => 'Tenant B', 'jenis' => 'prodi']),
        ];
    }
}
