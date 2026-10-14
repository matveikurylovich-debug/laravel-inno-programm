<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OutboxMessage;
use App\Models\ProductSnapshot;
use App\Services\OutboxService;

beforeEach(function () {
    mockRedis();
});

it('persists the created order and outbox row together', function () {
    ProductSnapshot::query()->create([
        'product_id' => 10,
        'store_id' => 1,
        'name' => 'Phone',
        'price' => 10.5,
        'is_active' => true,
    ]);

    $response = $this->withToken(orderJwt(email: 'user@example.com'))
        ->postJson('/api/orders', [
            'store_id' => 1,
            'items' => [
                ['product_id' => 10, 'quantity' => 2],
            ],
        ]);

    $response->assertCreated();
    $orderId = $response->json('data.id');

    expect(Order::query()->find($orderId))->not->toBeNull()
        ->and(OutboxMessage::query()->where('aggregate_id', $orderId)->count())->toBe(1)
        ->and(OutboxMessage::query()->where('aggregate_id', $orderId)->value('event_type'))->toBe('order.created')
        ->and(OutboxMessage::query()->where('aggregate_id', $orderId)->value('status'))->toBe('pending');
});

it('rolls the order back when the outbox write fails', function () {
    ProductSnapshot::query()->create([
        'product_id' => 10,
        'store_id' => 1,
        'name' => 'Phone',
        'price' => 10.5,
        'is_active' => true,
    ]);

    $this->mock(OutboxService::class, function ($mock): void {
        $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('outbox unavailable'));
    });

    $this->withToken(orderJwt(email: 'user@example.com'))
        ->postJson('/api/orders', [
            'store_id' => 1,
            'items' => [
                ['product_id' => 10, 'quantity' => 1],
            ],
        ])
        ->assertStatus(500);

    expect(Order::query()->count())->toBe(0)
        ->and(OutboxMessage::query()->count())->toBe(0);
});
