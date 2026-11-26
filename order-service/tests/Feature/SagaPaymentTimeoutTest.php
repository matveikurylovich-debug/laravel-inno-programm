<?php

declare(strict_types=1);

use App\Contracts\PaymentGateway;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\NonRetryableException;
use App\Jobs\RefundCancelledOrder;
use App\Messaging\Consumers\InventoryEventsConsumer;
use App\Messaging\FailedMessagePolicy;
use App\Models\Order;
use App\Models\OutboxMessage;
use App\Models\Payment;
use App\Services\Orders\OrderService;
use Illuminate\Support\Facades\Queue;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\ConsumedMessage;

beforeEach(function (): void {
    mockRedis();
    $this->mock(PaymentGateway::class)->shouldIgnoreMissing();
});

/**
 * @param  array<string, mixed>  $body
 */
function inventoryMessage(array $body): void
{
    $message = new ConsumedMessage(
        topicName: 'inventory.events',
        partition: 0,
        headers: [],
        body: $body,
        key: null,
        offset: 0,
        timestamp: time(),
    );

    resolve(InventoryEventsConsumer::class)($message, Kafka::consumer(['inventory.events'])->build());
}

it('does not create a second payment intent for a repeated reservation', function (): void {
    $order = Order::query()->create([
        'user_id' => 1,
        'email' => 'buyer@example.com',
        'store_id' => 1,
        'status' => OrderStatus::Created,
        'payment_status' => PaymentStatus::Pending,
        'total_amount' => 10,
        'currency' => 'usd',
    ]);

    $this->mock(PaymentGateway::class, function ($mock) use ($order): void {
        $mock->shouldReceive('createPaymentIntent')->once()->andReturnUsing(fn (): Payment => Payment::query()->create([
            'order_id' => $order->id,
            'stripe_payment_intent_id' => 'pi_once',
            'amount' => 10,
            'currency' => 'USD',
            'status' => PaymentStatus::Pending,
        ]));
    });

    inventoryMessage(['event' => 'stock.reserved', 'order_id' => $order->id]);
    inventoryMessage(['event' => 'stock.reserved', 'order_id' => $order->id]);

    expect(Payment::query()->count())->toBe(1)
        ->and($order->refresh()->status)->toBe(OrderStatus::PaymentPending);
});

it('confirms a declined card only after a later successful charge and stock commit', function (): void {
    $order = Order::query()->create([
        'user_id' => 1,
        'email' => 'buyer@example.com',
        'store_id' => 1,
        'status' => OrderStatus::PaymentPending,
        'payment_status' => PaymentStatus::Pending,
        'total_amount' => 12,
        'currency' => 'usd',
    ]);
    Payment::query()->create([
        'order_id' => $order->id,
        'stripe_payment_intent_id' => 'pi_retry',
        'amount' => 12,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
    ]);

    resolve(OrderService::class)->handlePaymentFailed(stripeIntent('pi_retry', $order->id));
    expect($order->refresh()->status)->toBe(OrderStatus::PaymentPending);

    resolve(OrderService::class)->handlePaymentSucceeded(stripeIntent('pi_retry', $order->id));
    expect($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and(OutboxMessage::query()->where('event_type', 'stock.commit_requested')->exists())->toBeTrue();

    inventoryMessage(['event' => 'stock.committed', 'order_id' => $order->id, 'store_id' => 1]);

    expect($order->refresh()->status)->toBe(OrderStatus::Confirmed);
});

it('refunds and cancels when the warehouse cannot commit stock', function (): void {
    Queue::fake([RefundCancelledOrder::class]);
    $order = Order::query()->create([
        'user_id' => 1,
        'email' => 'buyer@example.com',
        'store_id' => 1,
        'status' => OrderStatus::Paid,
        'payment_status' => PaymentStatus::Paid,
        'total_amount' => 12,
        'currency' => 'usd',
    ]);

    inventoryMessage(['event' => 'stock.commit_failed', 'order_id' => $order->id, 'store_id' => 1]);

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->cancel_reason)->toBe('stock_commit_failed');
    Queue::assertPushed(RefundCancelledOrder::class, 1);
});

it('cancels an unpaid order after the payment deadline', function (): void {
    $order = Order::query()->create([
        'user_id' => 1,
        'email' => 'buyer@example.com',
        'store_id' => 1,
        'status' => OrderStatus::PaymentPending,
        'payment_status' => PaymentStatus::Pending,
        'payment_expires_at' => now()->subMinute(),
        'total_amount' => 12,
        'currency' => 'usd',
    ]);
    $payment = Payment::query()->create([
        'order_id' => $order->id,
        'stripe_payment_intent_id' => 'pi_expire',
        'amount' => 12,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
    ]);

    $this->mock(PaymentGateway::class, function ($mock) use ($payment): void {
        $mock->shouldReceive('cancel')->once()->withArgs(fn (Payment $incoming): bool => $incoming->is($payment))->andReturn('canceled');
    });

    $this->artisan('order:expire-orders')->assertSuccessful();

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->cancel_reason)->toBe('payment_timeout')
        ->and(OutboxMessage::query()->where('event_type', 'stock.release_requested')->exists())->toBeTrue();
});

it('republishes order.created once and then cancels a stuck reservation', function (): void {
    $order = Order::query()->create([
        'user_id' => 1,
        'email' => 'buyer@example.com',
        'store_id' => 1,
        'status' => OrderStatus::Created,
        'payment_status' => PaymentStatus::Pending,
        'reserve_deadline' => now()->subMinute(),
        'total_amount' => 12,
        'currency' => 'usd',
    ]);
    $order->items()->create([
        'product_id' => 10,
        'product_name' => 'Phone',
        'unit_price' => 12,
        'quantity' => 1,
        'subtotal' => 12,
    ]);

    $this->mock(PaymentGateway::class);
    $this->artisan('order:expire-orders')->assertSuccessful();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Created)
        ->and($order->reserve_republished_at)->not->toBeNull()
        ->and(OutboxMessage::query()->where('event_type', 'order.created')->count())->toBe(1);

    $order->update(['reserve_deadline' => now()->subMinute()]);
    $this->artisan('order:expire-orders')->assertSuccessful();

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->cancel_reason)->toBe('reserve_timeout');
});

it('throws a non retryable exception when the inventory payload has no order id', function (): void {
    expect(fn () => inventoryMessage(['event' => 'stock.reserved']))
        ->toThrow(NonRetryableException::class);
});

it('reproduces C-ORD-02 when a handler exception retries before the dlq', function (): void {
    $attempts = 0;
    $trace = [];
    $result = new FailedMessagePolicy(2)->deliver(
        handler: function () use (&$attempts): void {
            $attempts++;
            throw new RuntimeException('catalog-postgres down');
        },
        commit: function () use (&$trace): void {
            $trace[] = 'commit';
        },
        dlq: function () use (&$trace): void {
            $trace[] = 'dlq';
        },
    );

    expect($result)->toBe('dlq')
        ->and($attempts)->toBe(3)
        ->and($trace)->toBe(['dlq', 'commit']);
});

it('sends an invalid payload to the dlq without retrying', function (): void {
    $attempts = 0;
    $trace = [];
    new FailedMessagePolicy(5)->deliver(
        handler: function () use (&$attempts): void {
            $attempts++;
            throw new NonRetryableException('missing order_id');
        },
        commit: function () use (&$trace): void {
            $trace[] = 'commit';
        },
        dlq: function () use (&$trace): void {
            $trace[] = 'dlq';
        },
    );

    expect($attempts)->toBe(1)
        ->and($trace)->toBe(['dlq', 'commit']);
});
