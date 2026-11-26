<?php

declare(strict_types=1);

use App\Exceptions\OrderNotCancellable;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;

it('renders problem json for unauthorized, invalid and unknown orders', function (): void {
    $this->getJson('/api/orders')
        ->assertUnauthorized()
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonPath('detail', 'Требуется авторизация.');

    $this->withToken(orderJwt())
        ->withHeader('Idempotency-Key', 'problem-json')
        ->postJson('/api/orders', [])
        ->assertUnprocessable()
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonStructure(['type', 'title', 'status', 'detail', 'errors']);

    $this->getJson('/api/orders/not-a-uuid')
        ->assertNotFound()
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonPath('title', 'Not Found')
        ->assertJsonPath('detail', 'Ресурс не найден.');

    $this->withToken(orderJwt())
        ->getJson('/api/orders/'.fake()->uuid())
        ->assertNotFound()
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonPath('title', 'Not Found')
        ->assertJsonPath('detail', 'Ресурс не найден.');
});

it('renders a non cancellable order as problem json', function (): void {
    $request = Request::create('/api/orders/00000000-0000-4000-8000-000000000001/cancel', 'POST');
    $response = resolve(ExceptionHandler::class)->render(
        $request,
        new OrderNotCancellable('Отмена недоступна'),
    );

    expect($response->getStatusCode())->toBe(409)
        ->and($response->headers->get('content-type'))->toContain('application/problem+json');

    $body = json_decode((string) $response->getContent(), true);
    expect($body['type'])->not->toBeEmpty()
        ->and($body['title'])->toBe('Conflict')
        ->and($body['status'])->toBe(409)
        ->and($body['detail'])->toBe('Отмена недоступна');
});
