<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OutboxMessage;
use App\Models\ProductSnapshot;
use Illuminate\Support\Facades\DB;

it('writes the order and outbox to postgres', function (): void {
    expect(config('database.default'))->toBe('pgsql')
        ->and(config('database.connections.pgsql.database'))->toBe(getenv('DB_DATABASE'));

    ProductSnapshot::query()->create([
        'product_id' => 10,
        'store_id' => 1,
        'name' => 'Phone',
        'price' => 10.5,
        'is_active' => true,
    ]);

    $response = $this->withToken(orderJwt(email: 'user@example.com'))
        ->withHeader('Idempotency-Key', 'postgres-create')
        ->postJson('/api/orders', [
            'email' => 'user@example.com',
            'store_id' => 1,
            'location_id' => 1,
            'items' => [
                ['product_id' => 10, 'quantity' => 1],
            ],
        ]);

    $response->assertCreated();
    $orderId = $response->json('data.id');

    expect(Order::query()->find($orderId))->not->toBeNull()
        ->and(OutboxMessage::query()->where('aggregate_id', $orderId)->value('event_type'))->toBe('order.created')
        ->and(DB::getDriverName())->toBe('pgsql');
});
