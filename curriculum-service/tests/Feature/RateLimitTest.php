<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_rate_limited_after_five_attempts_per_identity_and_ip(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'login' => 'unknown@example.test',
                'password' => 'incorrect-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', [
            'login' => 'unknown@example.test',
            'password' => 'incorrect-password',
        ])->assertTooManyRequests();
    }
}
