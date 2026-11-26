<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

use function Pest\Laravel\getJson;

it('renders validation, not-found, and unauthorized errors as problem+json', function (): void {
    getJson('/api/search?per_page=0')
        ->assertUnprocessable()
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonStructure(['type', 'title', 'status', 'detail', 'errors']);

    getJson('/api/does-not-exist')
        ->assertNotFound()
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonPath('detail', 'Ресурс не найден.');

    Route::get('/api/_problem-auth', function (): void {
        abort(401, 'Требуется авторизация.');
    });

    getJson('/api/_problem-auth')
        ->assertUnauthorized()
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonPath('detail', 'Требуется авторизация.');
});
