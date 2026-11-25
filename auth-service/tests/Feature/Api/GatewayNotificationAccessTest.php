<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GatewayNotificationAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::flushall();
        Role::create(['name' => 'customer']);
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'analyst']);
    }

    protected function tearDown(): void
    {
        Redis::flushall();
        parent::tearDown();
    }

    public function test_analyst_cannot_read_notifications(): void
    {
        Http::fake();

        $analyst = User::factory()->create();
        $analyst->assignRole('analyst');

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($analyst))
            ->getJson('/api/notifications')
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_admin_notification_read_is_proxied(): void
    {
        Http::fake([
            '*' => Http::response(['data' => []], 200),
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->getJson('/api/notifications')
            ->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'notifications'));
    }
}
