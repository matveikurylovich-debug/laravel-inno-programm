<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Support\Facades\Redis;

beforeEach(function (): void {
    Redis::shouldReceive('setex')->zeroOrMoreTimes()->andReturnTrue();
});

it('allows a mapped transition', function (OrderStatus $from, OrderStatus $to): void {
    $order = Order::query()->create([
        'user_id' => 1,
        'email' => 'buyer@example.com',
        'store_id' => 1,
        'status' => $from,
        'payment_status' => PaymentStatus::Pending,
        'total_amount' => 10,
        'currency' => 'usd',
    ]);

    $order->transitionTo($to);

    expect($order->fresh()->status)->toBe($to);
})->with([
    [OrderStatus::Created, OrderStatus::StockReserved],
    [OrderStatus::Created, OrderStatus::Cancelled],
    [OrderStatus::StockReserved, OrderStatus::PaymentPending],
    [OrderStatus::PaymentPending, OrderStatus::Paid],
    [OrderStatus::PaymentPending, OrderStatus::Cancelled],
    [OrderStatus::Paid, OrderStatus::Confirmed],
    [OrderStatus::Paid, OrderStatus::Cancelled],
    [OrderStatus::Confirmed, OrderStatus::Cancelled],
]);

it('rejects a forbidden transition', function (OrderStatus $from, OrderStatus $to): void {
    $order = Order::query()->create([
        'user_id' => 1,
        'email' => 'buyer@example.com',
        'store_id' => 1,
        'status' => $from,
        'payment_status' => PaymentStatus::Pending,
        'total_amount' => 10,
        'currency' => 'usd',
    ]);

    expect(fn () => $order->transitionTo($to))->toThrow(InvalidArgumentException::class);
    expect($order->fresh()->status)->toBe($from);
})->with([
    [OrderStatus::Cancelled, OrderStatus::PaymentPending],
    [OrderStatus::Confirmed, OrderStatus::Paid],
    [OrderStatus::Created, OrderStatus::Confirmed],
    [OrderStatus::Paid, OrderStatus::PaymentPending],
]);

it('sets the payment deadline when the order starts waiting for a card', function (): void {
    config(['orders.payment_ttl_minutes' => 30]);
    $order = Order::query()->create([
        'user_id' => 1,
        'email' => 'buyer@example.com',
        'store_id' => 1,
        'status' => OrderStatus::StockReserved,
        'payment_status' => PaymentStatus::Pending,
        'total_amount' => 10,
        'currency' => 'usd',
    ]);

    $order->transitionTo(OrderStatus::PaymentPending);

    expect($order->fresh()->payment_expires_at?->greaterThan(now()->addMinutes(29)))->toBeTrue();
});
