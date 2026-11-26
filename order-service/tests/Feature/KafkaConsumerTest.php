<?php

declare(strict_types=1);

use App\Contracts\PaymentGateway;
use App\Enums\OrderStatus;
use App\Exceptions\NonRetryableException;
use App\Messaging\ConsumerFactory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductSnapshot;
use Ecommerce\PlatformKit\Messaging\NonRetryableMessageException;
use Illuminate\Support\Sleep;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\ConsumedMessage;

beforeEach(function (): void {
    mockRedis();

    if (! defined('RD_KAFKA_PARTITION_UA')) {
        define('RD_KAFKA_PARTITION_UA', -1);
    }
});

it('handles stock reserved from fake kafka', function (): void {
    $order = Order::query()->create([
        'user_id' => 1,
        'email' => 'buyer@example.com',
        'store_id' => 1,
        'status' => 'created',
        'payment_status' => 'pending',
        'total_amount' => 9,
        'currency' => 'usd',
    ]);

    $this->mock(PaymentGateway::class, function ($mock): void {
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

    $this->artisan('order:consume-inventory-events')->assertSuccessful();

    expect($order->refresh()->status)->toBe(OrderStatus::PaymentPending)
        ->and($order->payment?->stripe_payment_intent_id)->toBe('pi_kafka');
});

it('updates a product snapshot from fake kafka', function (): void {
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

    $this->artisan('order:consume-catalog-events')->assertSuccessful();

    $snapshot = ProductSnapshot::query()->find(42);
    expect($snapshot)->not->toBeNull()
        ->and($snapshot?->name)->toBe('Tablet')
        ->and($snapshot?->store_id)->toBe(3)
        ->and($snapshot?->is_active)->toBeTrue();
});

it('retries transient failures in ConsumerFactory with Sleep backoff before rethrowing', function (): void {
    Sleep::fake();
    config([
        'kafka.failed_message_retries' => 2,
        'kafka.failed_message_backoff_ms' => 50,
    ]);

    Kafka::fake();
    Kafka::shouldReceiveMessages([
        new ConsumedMessage(
            topicName: 'inventory.events',
            partition: 0,
            headers: [],
            body: ['event' => 'stock.reserved'],
            key: 'k1',
            offset: 0,
            timestamp: time(),
        ),
    ]);

    $attempts = 0;

    expect(function () use (&$attempts): void {
        ConsumerFactory::consume(
            'inventory.events',
            'test-group',
            'inventory.events.dlq.order-service',
            static function () use (&$attempts): void {
                $attempts++;
                throw new RuntimeException('db temporarily unavailable');
            },
        );
    })->toThrow(RuntimeException::class, 'db temporarily unavailable');

    expect($attempts)->toBe(3);
    Sleep::assertSleptTimes(2);
});

it('rethrows non-retryable exceptions immediately in ConsumerFactory without retrying', function (Throwable $exception): void {
    Sleep::fake();
    config([
        'kafka.failed_message_retries' => 5,
        'kafka.failed_message_backoff_ms' => 100,
    ]);

    Kafka::fake();
    Kafka::shouldReceiveMessages([
        new ConsumedMessage(
            topicName: 'catalog.events',
            partition: 0,
            headers: [],
            body: [],
            key: 'k2',
            offset: 0,
            timestamp: time(),
        ),
    ]);

    $attempts = 0;

    expect(function () use (&$attempts, $exception): void {
        ConsumerFactory::consume(
            'catalog.events',
            'test-group',
            'catalog.events.dlq.order-service',
            static function () use (&$attempts, $exception): void {
                $attempts++;
                throw $exception;
            },
        );
    })->toThrow($exception::class);

    expect($attempts)->toBe(1);
    Sleep::assertNeverSlept();
})->with([
    'domain non-retryable' => fn (): NonRetryableException => new NonRetryableException('bad payload'),
    'platform-kit non-retryable' => fn (): NonRetryableMessageException => new NonRetryableMessageException('bad envelope'),
]);
