<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductSnapshot;
use App\Services\StripeService;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\ConsumedMessage;

beforeEach(function () {
    mockRedis();

    if (! defined('RD_KAFKA_PARTITION_UA')) {
        define('RD_KAFKA_PARTITION_UA', -1);
    }
});

it('handles stock reserved from fake kafka', function () {
    $order = Order::query()->create([
        'user_id' => 1,
        'email' => 'buyer@example.com',
        'store_id' => 1,
        'status' => 'created',
        'payment_status' => 'pending',
        'total_amount' => 9,
        'currency' => 'usd',
    ]);

    $this->mock(StripeService::class, function ($mock): void {
        $mock->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturnUsing(fn (Order $incoming): Payment => Payment::query()->create([
                'order_id' => $incoming->id,
                'stripe_payment_intent_id' => 'pi_kafka',
                'stripe_client_secret' => 'secret',
                'amount' => $incoming->total_amount,
                'currency' => 'USD',
                'status' => 'pending',
            ]));
    });

    Kafka::fake();
    Kafka::shouldReceiveMessages([
        new ConsumedMessage(
            topicName: 'inventory.events',
            partition: 0,
            headers: ['event-type' => 'stock.reserved'],
            body: [
                'event' => 'stock.reserved',
                'order_id' => $order->id,
            ],
            key: $order->id,
            offset: 0,
            timestamp: time(),
        ),
    ]);

    $this->artisan('kafka:consume-inventory')->assertSuccessful();

    expect($order->refresh()->status)->toBe('payment_pending')
        ->and($order->payment?->stripe_payment_intent_id)->toBe('pi_kafka');
});

it('updates a product snapshot from fake kafka', function () {
    Kafka::fake();
    Kafka::shouldReceiveMessages([
        new ConsumedMessage(
            topicName: 'catalog.events',
            partition: 0,
            headers: ['event-type' => 'product.updated'],
            body: [
                'event' => 'product.updated',
                'product_id' => 42,
                'store_id' => 3,
                'name' => 'Tablet',
                'price' => 19.5,
                'is_active' => true,
            ],
            key: '42',
            offset: 1,
            timestamp: time(),
        ),
    ]);

    $this->artisan('kafka:consume-catalog')->assertSuccessful();

    $snapshot = ProductSnapshot::query()->find(42);
    expect($snapshot)->not->toBeNull()
        ->and($snapshot->name)->toBe('Tablet')
        ->and($snapshot->store_id)->toBe(3)
        ->and($snapshot->is_active)->toBeTrue();
});
