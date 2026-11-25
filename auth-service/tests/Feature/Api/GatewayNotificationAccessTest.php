<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GatewayNotificationAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'customer']);
    }

    public function test_verify_requires_a_token(): void
    {
        $this->getJson('/api/gateway/verify')->assertUnauthorized();
    }

    public function test_verify_accepts_a_live_access_token(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');
        $token = $this->tokenFor($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/gateway/verify')
            ->assertNoContent()
            ->assertHeader('X-Access-Token', $token);
    }
}
