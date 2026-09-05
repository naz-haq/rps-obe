<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_remains_public(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_business_endpoint_rejects_anonymous_requests(): void
    {
        $this->getJson('/api/v1/generate-sessions')
            ->assertUnauthorized();
    }

    public function test_business_endpoint_rejects_an_invalid_bearer_token(): void
    {
        $this->withToken('invalid-audit-token')
            ->getJson('/api/v1/generate-sessions')
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_access_business_endpoint(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/generate-sessions')
            ->assertOk();
    }
}
