<?php

declare(strict_types=1);

use App\Contracts\PaymentGateway;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Messaging\Consumers\InventoryEventsConsumer;
use App\Models\Order;
use App\Models\OutboxMessage;
use App\Models\Payment;
use App\Services\Orders\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\ConsumedMessage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    mockRedis();
});

it('moves an order from created to confirmed only after the stock commit', function (): void {
    $order = Order::query()->create([
        'user_id' => 1,
        'email' => 'buyer@example.com',
        'store_id' => 1,
        'status' => 'created',
        'payment_status' => 'pending',
        'total_amount' => 15,
        'currency' => 'usd',
    ]);

    $this->mock(PaymentGateway::class, function ($mock) use ($order): void {
        $mock->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturnUsing(fn (Order $incoming): Payment => Payment::query()->create([
                'order_id' => $order->id,
                'stripe_payment_intent_id' => 'pi_saga',
                'stripe_client_secret' => 'pi_saga_secret',
                'amount' => $incoming->total_amount,
                'currency' => 'USD',
                'status' => 'pending',
            ]));
    });

    $consumer = Kafka::consumer(['inventory.events'])->build();

    $message = new ConsumedMessage(
        topicName: 'inventory.events',
        partition: 0,
        headers: [],
        body: [
            'event' => 'stock.reserved',
            'order_id' => $order->id,
        ],
        key: null,
        offset: 0,
        timestamp: time(),
    );

    resolve(InventoryEventsConsumer::class)($message, $consumer);

    expect($order->refresh()->status)->toBe(OrderStatus::PaymentPending)
        ->and($order->payment_expires_at)->not->toBeNull();

    resolve(OrderService::class)->handlePaymentSucceeded(stripeIntent('pi_saga', $order->id));

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and(OutboxMessage::query()->orderBy('id')->pluck('event_type')->all())->toBe(['order.paid', 'stock.commit_requested']);

    $committed = new ConsumedMessage(
        topicName: 'inventory.events',
        partition: 0,
        headers: [],
        body: [
            'event' => 'stock.committed',
            'order_id' => $order->id,
        ],
        key: null,
        offset: 1,
        timestamp: time(),
    );
    resolve(InventoryEventsConsumer::class)($committed, $consumer);

    expect($order->refresh()->status)->toBe(OrderStatus::Confirmed)
        ->and(OutboxMessage::query()->orderBy('id')->pluck('event_type')->all())->toBe(['order.paid', 'stock.commit_requested', 'order.confirmed']);
});

it('ignores stock reservation once the order has left created', function (): void {
    $order = Order::query()->create([
        'user_id' => 1,
        'email' => 'buyer@example.com',
        'store_id' => 1,
        'status' => 'cancelled',
        'payment_status' => 'pending',
        'total_amount' => 15,
        'currency' => 'usd',
    ]);

    $message = new ConsumedMessage(
        topicName: 'inventory.events',
        partition: 0,
        headers: [],
        body: [
            'event' => 'stock.reserved',
            'order_id' => $order->id,
        ],
        key: null,
        offset: 0,
        timestamp: time(),
    );

    resolve(InventoryEventsConsumer::class)($message, Kafka::consumer(['inventory.events'])->build());

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and(Payment::query()->count())->toBe(0);
});
