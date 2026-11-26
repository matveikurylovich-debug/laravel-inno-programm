<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

it('renders catalog API errors as problem+json', function (): void {
    $this->postJson('/api/v1/stores', [])
        ->assertUnauthorized()
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonPath('status', 401)
        ->assertJsonPath('detail', 'Требуется авторизация.');

    Route::post('/api/v1/_problem', static function (Request $request): void {
        $request->validate(['name' => ['required', 'string']]);
    });

    $invalid = $this->postJson('/api/v1/_problem', []);
    $invalid->assertUnprocessable()
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonStructure(['detail', 'errors']);
    expect($invalid->json('detail'))->not->toBe('Unprocessable Entity');

    $missing = $this->getJson('/api/v1/products/999999');
    $missing->assertNotFound()
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonPath('detail', 'Товар не найден');
    expect((string) $missing->getContent())->not->toContain('App\\Models');
});
