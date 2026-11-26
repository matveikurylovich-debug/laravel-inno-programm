<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

it('renders authentication, routing, and validation errors as RFC 7807 problem+json in Russian', function (): void {
    getJson('/api/v1/notifications')
        ->assertUnauthorized()
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonPath('detail', 'Требуется авторизация.');

    getJson('/api/does-not-exist')
        ->assertNotFound()
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonPath('detail', 'Ресурс не найден.');

    Route::post('/api/_problem', static function (): void {
        request()->validate(['email' => ['required', 'email']]);
    });

    $invalid = postJson('/api/_problem', []);
    $invalid->assertUnprocessable()
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonStructure(['detail', 'errors']);

    expect($invalid->json('detail'))->not->toBe('Unprocessable Entity');
});
