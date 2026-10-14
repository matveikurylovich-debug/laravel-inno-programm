<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OutboxMessage;
use App\Models\Payment;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    mockRedis();
});

it('requests stock release after a failed payment', function () {
    $order = Order::query()->create([
        'user_id' => 1,
        'email' => 'buyer@example.com',
        'store_id' => 4,
        'status' => 'payment_pending',
        'payment_status' => 'pending',
        'total_amount' => 20,
        'currency' => 'usd',
    ]);

    Payment::query()->create([
        'order_id' => $order->id,
        'stripe_payment_intent_id' => 'pi_fail',
        'amount' => 20,
        'currency' => 'USD',
        'status' => 'pending',
    ]);

    app(OrderService::class)->handlePaymentFailed([
        'id' => 'pi_fail',
        'metadata' => ['order_id' => $order->id],
    ]);

    $order->refresh();
    $release = OutboxMessage::query()->where('event_type', 'stock.release_requested')->first();

    expect($order->status)->toBe('payment_failed')
        ->and($order->payment->status)->toBe('failed')
        ->and($release)->not->toBeNull()
        ->and($release->payload['order_id'])->toBe($order->id)
        ->and($release->payload['store_id'])->toBe(4)
        ->and(OutboxMessage::query()->where('event_type', 'order.payment_failed')->exists())->toBeTrue();
});
