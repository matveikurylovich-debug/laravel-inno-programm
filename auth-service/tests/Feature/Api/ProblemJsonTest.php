<?php

declare(strict_types=1);

it('renders authentication, validation, and 404 errors as RFC 9457 problem+json', function (): void {
    $this->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonPath('title', 'Unauthorized')
        ->assertJsonPath('status', 401)
        ->assertJsonPath('detail', 'Требуется авторизация.');

    $invalid = $this->postJson('/api/v1/register', []);
    $invalid->assertUnprocessable()
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonPath('title', 'Unprocessable Entity')
        ->assertJsonStructure(['type', 'title', 'status', 'detail', 'errors']);

    expect($invalid->json('detail'))->not->toBe('')
        ->and($invalid->json('detail'))->not->toBe('Unprocessable Entity');

    $missing = $this->getJson('/api/v1/does-not-exist');
    $missing->assertNotFound()
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonPath('detail', 'Ресурс не найден.');

    expect((string) $missing->getContent())->not->toContain('App\\');
});
