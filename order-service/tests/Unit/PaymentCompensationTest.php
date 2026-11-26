<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OutboxMessage;
use App\Models\Payment;
use App\Services\Orders\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    mockRedis();
});

it('keeps the reservation when a card is declined', function (): void {
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

    resolve(OrderService::class)->handlePaymentFailed(stripeIntent('pi_fail', $order->id));

    $order->refresh();
    $release = OutboxMessage::query()->where('event_type', 'stock.release_requested')->first();

    expect($order->status)->toBe(OrderStatus::PaymentPending)
        ->and($order->payment_status)->toBe(PaymentStatus::Failed)
        ->and($order->payment->status)->toBe(PaymentStatus::Failed)
        ->and($release)->toBeNull()
        ->and(OutboxMessage::query()->where('event_type', 'order.payment_failed')->exists())->toBeTrue();
});
