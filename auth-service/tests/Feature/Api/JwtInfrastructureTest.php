<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\JwtService;
use Ecommerce\PlatformKit\Laravel\RevocationInspector;
use Ecommerce\PlatformKit\Token\AccessClaims;
use Ecommerce\PlatformKit\Token\TokenInfrastructureException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class JwtInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_revocation_store_failure_is_a_server_error_without_the_internal_message(): void
    {
        $user = User::factory()->create();
        $token = app(JwtService::class)->createAccessToken($user);

        $this->app->singleton(RevocationInspector::class, function (): RevocationInspector {
            return new class implements RevocationInspector
            {
                public function assertActive(AccessClaims $claims, string $token): void
                {
                    throw new TokenInfrastructureException('redis connection refused at 10.0.0.8');
                }
            };
        });
        $this->app['auth']->forgetGuards();
        $logged = false;
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
            if ($event->level === 'error' && $event->message === 'JWT infrastructure failure') {
                $logged = true;
            }
        });

        $response = $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/me');

        $response->assertStatus(500)
            ->assertJson(['message' => 'Server Error']);
        $this->assertStringNotContainsString('redis connection refused', (string) $response->getContent());
        $this->assertTrue($logged);
    }
}
