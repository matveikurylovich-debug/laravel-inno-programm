<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    RateLimiter::clear('user@example.com|203.0.113.10');
    RateLimiter::clear('user@example.com|203.0.113.11');
});

it('resolves client IP from X-Forwarded-For only for trusted gateway proxies', function (): void {
    Route::middleware('api')->get('/api/v1/test-edge-ip', fn (Request $request) => response()->json([
        'ip' => $request->ip(),
        'secure' => $request->isSecure(),
    ]))->name('api.v1.test-edge-ip');

    $trusted = $this->withServerVariables(['REMOTE_ADDR' => '172.28.0.2'])
        ->withHeaders([
            'X-Forwarded-For' => '203.0.113.10',
            'X-Forwarded-Proto' => 'https',
        ])
        ->getJson('/api/v1/test-edge-ip');

    $trusted->assertOk()
        ->assertJsonPath('ip', '203.0.113.10')
        ->assertJsonPath('secure', true);

    $untrusted = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.99'])
        ->withHeaders([
            'X-Forwarded-For' => '203.0.113.10',
        ])
        ->getJson('/api/v1/test-edge-ip');

    $untrusted->assertOk()
        ->assertJsonPath('ip', '198.51.100.99');
});

it('throttles the sixth login attempt per client IP behind a trusted proxy with Retry-After while allowing another IP', function (): void {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => Hash::make('correct-password'),
    ]);
    $user->assignRole('customer');

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->withServerVariables(['REMOTE_ADDR' => '172.28.0.2'])
            ->withHeader('X-Forwarded-For', '203.0.113.10')
            ->postJson('/api/v1/login', [
                'email' => 'USER@example.com',
                'password' => 'wrong-password',
            ])
            ->assertUnauthorized();
    }

    $throttled = $this->withServerVariables(['REMOTE_ADDR' => '172.28.0.2'])
        ->withHeader('X-Forwarded-For', '203.0.113.10')
        ->postJson('/api/v1/login', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ]);

    $throttled->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertHeader('content-type', 'application/problem+json');

    expect((int) $throttled->headers->get('Retry-After'))->toBeGreaterThan(0);

    $this->withServerVariables(['REMOTE_ADDR' => '172.28.0.2'])
        ->withHeader('X-Forwarded-For', '203.0.113.11')
        ->postJson('/api/v1/login', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ])
        ->assertUnauthorized();
});
