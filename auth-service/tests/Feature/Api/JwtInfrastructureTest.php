<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Auth\JwtService;
use Ecommerce\PlatformKit\Laravel\RevocationInspector;
use Ecommerce\PlatformKit\Token\AccessClaims;
use Ecommerce\PlatformKit\Token\TokenInfrastructureException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;

it('renders revocation store failure as 500 problem+json without leaking internal details', function (): void {
    $user = User::factory()->create();
    $token = resolve(JwtService::class)->generateTokenPair($user)->accessToken;

    $this->app->singleton(RevocationInspector::class, fn (): RevocationInspector => new class implements RevocationInspector
    {
        public function assertActive(AccessClaims $claims, string $token): void
        {
            throw new TokenInfrastructureException('redis connection refused at 10.0.0.8');
        }
    });
    $this->app['auth']->forgetGuards();

    $logged = false;
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
        if ($event->level === 'error' && $event->message === 'JWT infrastructure failure') {
            $logged = true;
        }
    });

    $response = $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/me');

    $response->assertStatus(500)
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonPath('detail', 'Внутренняя ошибка сервера.');

    expect((string) $response->getContent())->not->toContain('redis connection refused')
        ->and($logged)->toBeTrue();
});
