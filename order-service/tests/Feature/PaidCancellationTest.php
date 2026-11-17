<?php

declare(strict_types=1);

use App\Jobs\RefundCancelledOrder;
use App\Models\Order;
use App\Models\OutboxMessage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

it('cancels a paid order once and queues stock release and refund', function () {
    Redis::shouldReceive('setex')->andReturn(true);
    Queue::fake([RefundCancelledOrder::class]);
    $order = Order::create(['user_id' => 1, 'email' => 'buyer@example.com', 'store_id' => 1, 'location_id' => 1,
        'status' => 'confirmed', 'payment_status' => 'paid', 'total_amount' => 20, 'currency' => 'usd']);
    $this->withToken(orderJwt())->postJson('/api/orders/'.$order->id.'/cancel')->assertOk()->assertJsonPath('data.status', 'cancelled');
    $this->withToken(orderJwt())->postJson('/api/orders/'.$order->id.'/cancel')->assertOk();
    expect(OutboxMessage::where('event_type', 'stock.release_requested')->count())->toBe(1);
    expect(OutboxMessage::where('event_type', 'order.cancelled')->count())->toBe(1);
    Queue::assertPushed(RefundCancelledOrder::class, 1);
});

it('soft deletes a paid order with cancellation events instead of losing its financial record', function () {
    Redis::shouldReceive('setex')->andReturn(true);
    Queue::fake([RefundCancelledOrder::class]);
    $order = Order::create(['user_id' => 1, 'email' => 'buyer@example.com', 'store_id' => 1, 'location_id' => 1,
        'status' => 'paid', 'payment_status' => 'paid', 'total_amount' => 20, 'currency' => 'usd']);
    $this->withToken(orderJwt())->deleteJson('/api/orders/'.$order->id)->assertNoContent();
    $this->assertSoftDeleted($order);
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
    expect(OutboxMessage::where('event_type', 'order.cancelled')->count())->toBe(1);
    Queue::assertPushed(RefundCancelledOrder::class, 1);
});
