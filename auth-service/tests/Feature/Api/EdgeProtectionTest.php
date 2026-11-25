<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EdgeProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_trusted_proxy_uses_the_forwarded_client_ip(): void
    {
        $this->withServerVariables([
            'REMOTE_ADDR' => '172.28.0.2',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ])->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ]);

        $this->assertSame('203.0.113.10', request()->ip());
    }

    public function test_sixth_login_from_one_ip_is_rate_limited_and_another_ip_is_not(): void
    {
        $user = User::factory()->create(['email' => 'limited@example.com']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
                ->postJson('/api/login', [
                    'email' => $user->email,
                    'password' => 'wrong-password',
                ])->assertUnauthorized();
        }

        $limited = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);

        $limited->assertStatus(429);
        $this->assertNotSame('', (string) $limited->headers->get('Retry-After'));

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.21'])
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertUnauthorized();
    }

    public function test_dot_segments_in_the_catalog_proxy_are_not_found(): void
    {
        $this->call('GET', '/api/catalog/..%2Fcatalog-admin')->assertNotFound();
    }
}
