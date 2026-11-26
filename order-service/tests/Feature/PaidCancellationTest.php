<?php

declare(strict_types=1);

use App\Jobs\RefundCancelledOrder;
use App\Models\Order;
use App\Models\OutboxMessage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

it('cancels a paid order once and queues stock release and refund on payments queue', function (): void {
    Redis::shouldReceive('setex')->andReturn(true);
    Queue::fake([RefundCancelledOrder::class]);
    $order = Order::query()->create([
        'user_id' => 1,
        'email' => 'buyer@example.com',
        'store_id' => 1,
        'location_id' => 1,
        'status' => 'confirmed',
        'payment_status' => 'paid',
        'total_amount' => 20,
        'currency' => 'usd',
    ]);

    $this->withToken(orderJwt())->postJson('/api/orders/'.$order->id.'/cancel')->assertOk()->assertJsonPath('data.status', 'cancelled');
    $this->withToken(orderJwt())->postJson('/api/orders/'.$order->id.'/cancel')->assertOk();

    expect(OutboxMessage::query()->where('event_type', 'stock.release_requested')->count())->toBe(1);
    $cancelledEvent = OutboxMessage::query()->where('event_type', 'order.cancelled')->first();
    expect($cancelledEvent)->not->toBeNull()
        ->and($cancelledEvent?->payload['refunded_amount'])->toBe('0.00');
    Queue::assertPushedOn('payments', RefundCancelledOrder::class);
    Queue::assertPushed(RefundCancelledOrder::class, 1);
});

it('transitions a paid order to cancelled while keeping its financial record intact', function (): void {
    Redis::shouldReceive('setex')->andReturn(true);
    Queue::fake([RefundCancelledOrder::class]);
    $order = Order::query()->create([
        'user_id' => 1,
        'email' => 'buyer@example.com',
        'store_id' => 1,
        'location_id' => 1,
        'status' => 'paid',
        'payment_status' => 'paid',
        'total_amount' => 20,
        'currency' => 'usd',
    ]);

    $this->withToken(orderJwt())
        ->postJson('/api/orders/'.$order->id.'/cancel')
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
    expect(Order::query()->find($order->id))->not->toBeNull()
        ->and(OutboxMessage::query()->where('event_type', 'order.cancelled')->count())->toBe(1);
    Queue::assertPushedOn('payments', RefundCancelledOrder::class);
});
