<?php

declare(strict_types=1);

use App\Kafka\Consumers\InventoryEventsConsumer;
use App\Models\Order;
use App\Models\OutboxMessage;
use App\Models\Payment;
use App\Services\OrderService;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\MessageConsumer;

uses(RefreshDatabase::class);

$sagaSteps = [];

beforeEach(function () use (&$sagaSteps) {
    $sagaSteps = [];

    Redis::shouldReceive('setex')
        ->zeroOrMoreTimes()
        ->andReturnUsing(function (string $key, int $ttl, mixed $value) use (&$sagaSteps): bool {
            $sagaSteps[] = (string) $value;

            return true;
        });
});

it('moves an order from created to confirmed', function () use (&$sagaSteps) {
    $order = Order::query()->create([
        'user_id' => 1,
        'email' => 'buyer@example.com',
        'store_id' => 1,
        'status' => 'created',
        'payment_status' => 'pending',
        'total_amount' => 15,
        'currency' => 'usd',
    ]);

    $this->mock(StripeService::class, function ($mock) use ($order): void {
        $mock->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturnUsing(function (Order $incoming) use ($order): Payment {
                return Payment::query()->create([
                    'order_id' => $order->id,
                    'stripe_payment_intent_id' => 'pi_saga',
                    'stripe_client_secret' => 'pi_saga_secret',
                    'amount' => $incoming->total_amount,
                    'currency' => 'USD',
                    'status' => 'pending',
                ]);
            });
    });

    $message = Mockery::mock(ConsumerMessage::class);
    $message->shouldReceive('getBody')->andReturn([
        'event' => 'stock.reserved',
        'order_id' => $order->id,
    ]);
    $message->shouldReceive('getHeaders')->andReturn([]);

    app(InventoryEventsConsumer::class)($message, Mockery::mock(MessageConsumer::class));

    expect($order->refresh()->status)->toBe('payment_pending');

    app(OrderService::class)->handlePaymentSucceeded([
        'id' => 'pi_saga',
        'metadata' => ['order_id' => $order->id],
    ]);

    $order->refresh();
    expect($order->status)->toBe('confirmed')
        ->and($order->payment_status)->toBe('paid')
        ->and($sagaSteps)->toBe(['stock_reserved', 'payment_pending', 'paid', 'confirmed'])
        ->and(OutboxMessage::query()->orderBy('id')->pluck('event_type')->all())->toBe(['order.paid', 'order.confirmed']);
});
