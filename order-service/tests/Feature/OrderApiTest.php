<?php

declare(strict_types=1);

use App\Kafka\Consumers\InventoryEventsConsumer;
use App\Models\Order;
use App\Models\OutboxMessage;
use App\Models\Payment;
use App\Models\ProductSnapshot;
use App\Services\OrderService;
use App\Services\StripeService;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\MessageConsumer;

$redisStore = [];

beforeEach(function () use (&$redisStore) {
    $redisStore = [];

    Redis::shouldReceive('setex')
        ->zeroOrMoreTimes()
        ->andReturnUsing(function (string $key, int $ttl, mixed $value) use (&$redisStore): bool {
            $redisStore[$key] = $value;

            return true;
        });

    Redis::shouldReceive('get')
        ->zeroOrMoreTimes()
        ->andReturnUsing(function (string $key) use (&$redisStore) {
            return $redisStore[$key] ?? null;
        });

    Redis::shouldReceive('set')
        ->zeroOrMoreTimes()
        ->andReturnUsing(function (string $key, mixed $value, ...$args) use (&$redisStore): bool {
            if (in_array('NX', $args, true) && array_key_exists($key, $redisStore)) {
                return false;
            }

            $redisStore[$key] = $value;

            return true;
        });

    Redis::shouldReceive('del')
        ->zeroOrMoreTimes()
        ->andReturnUsing(function (string $key) use (&$redisStore): int {
            $existed = array_key_exists($key, $redisStore);
            unset($redisStore[$key]);

            return $existed ? 1 : 0;
        });

    $this->mock(StripeService::class);
});

function apiSnapshot(): void
{
    ProductSnapshot::query()->create([
        'product_id' => 10,
        'store_id' => 1,
        'name' => 'Phone',
        'price' => 10.5,
        'is_active' => true,
    ]);
}

/**
 * @param  array<string, mixed>  $body
 */
function dispatchInventory(array $body): void
{
    $message = Mockery::mock(ConsumerMessage::class);
    $message->shouldReceive('getBody')->andReturn($body);
    $message->shouldReceive('getHeaders')->andReturn([]);

    app(InventoryEventsConsumer::class)($message, Mockery::mock(MessageConsumer::class));
}

it('creates an order from a snapshot and records outbox', function () {
    apiSnapshot();

    $response = $this->withToken(orderJwt(email: 'user@example.com'))
        ->postJson('/api/orders', [
            'store_id' => 1,
            'location_id' => 1,
            'items' => [
                ['product_id' => 10, 'quantity' => 2],
            ],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.store_id', 1)
        ->assertJsonPath('data.status', 'created')
        ->assertJsonPath('data.payment_status', 'pending')
        ->assertJsonPath('data.total_amount', 21)
        ->assertJsonPath('data.items.0.product_id', 10)
        ->assertJsonPath('data.items.0.quantity', 2);

    $message = OutboxMessage::query()->first();
    expect($message)->not->toBeNull()
        ->and($message->event_type)->toBe('order.created')
        ->and($message->status)->toBe('pending')
        ->and($message->payload['email'])->toBe('user@example.com')
        ->and($message->payload['data']['order_id'])->toBe($response->json('data.id'))
        ->and($message->payload['data']['total_amount'])->toEqual(21)
        ->and($message->payload['items'][0]['product_id'])->toBe(10)
        ->and($message->payload['items'][0]['quantity'])->toBe(2);
});

it('rejects an order when the snapshot is missing', function () {
    $this->withToken(orderJwt(email: 'user@example.com'))
        ->postJson('/api/orders', [
            'store_id' => 1,
            'location_id' => 1,
            'items' => [
                ['product_id' => 99, 'quantity' => 1],
            ],
        ])
        ->assertStatus(422)
        ->assertJsonPath('error', 'Unprocessable');

    expect(Order::query()->count())->toBe(0);
});

it('replays the same response for an idempotency key', function () {
    apiSnapshot();
    $key = (string) Str::uuid();
    $payload = [
        'store_id' => 1,
        'location_id' => 1,
        'items' => [
            ['product_id' => 10, 'quantity' => 1],
        ],
    ];

    $created = $this->withToken(orderJwt(email: 'user@example.com'))
        ->withHeader('Idempotency-Key', $key)
        ->postJson('/api/orders', $payload)
        ->assertCreated();

    $this->withToken(orderJwt(email: 'user@example.com'))
        ->withHeader('Idempotency-Key', $key)
        ->postJson('/api/orders', $payload)
        ->assertCreated()
        ->assertHeader('X-Idempotent-Replay', 'true')
        ->assertJsonPath('data.id', $created->json('data.id'));

    expect(Order::query()->count())->toBe(1);
});

it('hides another customers order', function () {
    apiSnapshot();

    $orderId = $this->withToken(orderJwt(5, email: 'user@example.com'))
        ->postJson('/api/orders', [
            'store_id' => 1,
            'location_id' => 1,
            'items' => [
                ['product_id' => 10, 'quantity' => 1],
            ],
        ])
        ->json('data.id');

    $this->withToken(orderJwt(6, email: 'user@example.com'))
        ->getJson("/api/orders/{$orderId}")
        ->assertForbidden();

    $this->withToken(orderJwt(6, 'admin', 'user@example.com'))
        ->getJson("/api/orders/{$orderId}")
        ->assertOk()
        ->assertJsonPath('data.id', $orderId);
});

it('moves a reserved order to payment', function () use (&$redisStore) {
    $order = Order::query()->create([
        'user_id' => 1,
        'store_id' => 1,
        'status' => 'created',
        'payment_status' => 'pending',
        'total_amount' => 10,
        'currency' => 'usd',
    ]);

    $this->app->instance(StripeService::class, Mockery::mock(StripeService::class, function ($mock) use ($order): void {
        $mock->shouldReceive('createPaymentIntent')
            ->once()
            ->with(Mockery::on(fn (Order $incoming): bool => $incoming->id === $order->id))
            ->andReturnUsing(function (Order $incoming): Payment {
                return Payment::query()->create([
                    'order_id' => $incoming->id,
                    'stripe_payment_intent_id' => 'pi_test_1',
                    'stripe_client_secret' => 'secret',
                    'amount' => $incoming->total_amount,
                    'currency' => 'USD',
                    'status' => 'pending',
                ]);
            });
    }));

    dispatchInventory([
        'event' => 'stock.reserved',
        'order_id' => $order->id,
        'store_id' => 1,
    ]);

    $order->refresh();
    expect($order->status)->toBe('payment_pending')
        ->and($order->payment?->stripe_payment_intent_id)->toBe('pi_test_1')
        ->and(OutboxMessage::query()->count())->toBe(0)
        ->and($redisStore["order:saga:{$order->id}"] ?? null)->toBe('payment_pending');
});

it('cancels an order when stock reservation fails', function () {
    $order = Order::query()->create([
        'user_id' => 1,
        'store_id' => 1,
        'status' => 'created',
        'payment_status' => 'pending',
        'total_amount' => 10,
        'currency' => 'usd',
    ]);

    dispatchInventory([
        'event' => 'stock.reservation_failed',
        'order_id' => $order->id,
        'reason' => 'Insufficient stock',
    ]);

    $order->refresh();
    $message = OutboxMessage::query()->first();

    expect($order->status)->toBe('cancelled')
        ->and($order->cancel_reason)->toBe('stock_unavailable')
        ->and($message)->not->toBeNull()
        ->and($message->event_type)->toBe('order.cancelled')
        ->and($message->payload['reason'])->toBe('stock_unavailable')
        ->and($message->payload['data']['order_id'])->toBe($order->id)
        ->and($message->payload['data']['reason'])->toBe('stock_unavailable');
});

it('confirms a paid order and writes outbox', function () {
    $order = Order::query()->create([
        'user_id' => 1,
        'store_id' => 1,
        'status' => 'payment_pending',
        'payment_status' => 'pending',
        'total_amount' => 21.0,
        'currency' => 'usd',
    ]);

    Payment::query()->create([
        'order_id' => $order->id,
        'stripe_payment_intent_id' => 'pi_success_1',
        'stripe_client_secret' => 'secret',
        'amount' => 21.0,
        'currency' => 'USD',
        'status' => 'pending',
    ]);

    app(OrderService::class)->handlePaymentSucceeded([
        'id' => 'pi_success_1',
        'metadata' => ['order_id' => $order->id],
    ]);

    $order->refresh();
    $events = OutboxMessage::query()->orderBy('id')->get();

    expect($order->status)->toBe('confirmed')
        ->and($order->payment_status)->toBe('paid')
        ->and($order->payment->status)->toBe('succeeded')
        ->and($events->pluck('event_type')->all())->toBe(['order.paid', 'order.confirmed'])
        ->and($events->first()->payload['data']['order_id'])->toBe($order->id)
        ->and($events->first()->payload['data']['total_amount'])->toEqual(21);
});

it('confirms a paid order after stock commit', function () use (&$redisStore) {
    $order = Order::query()->create([
        'user_id' => 1,
        'store_id' => 1,
        'status' => 'paid',
        'payment_status' => 'paid',
        'total_amount' => 21.0,
        'currency' => 'usd',
        'email' => 'buyer@example.com',
    ]);

    dispatchInventory([
        'event' => 'stock.committed',
        'order_id' => $order->id,
        'store_id' => 1,
    ]);

    $order->refresh();
    $message = OutboxMessage::query()->first();

    expect($order->status)->toBe('confirmed')
        ->and($message->event_type)->toBe('order.confirmed')
        ->and($message->payload['email'])->toBe('buyer@example.com')
        ->and($message->payload['data']['order_id'])->toBe($order->id)
        ->and($message->payload['data']['total_amount'])->toEqual(21)
        ->and($redisStore["order:saga:{$order->id}"] ?? null)->toBe('confirmed');
});

it('does not reprocess payment succeeded for a confirmed order', function () {
    $order = Order::query()->create([
        'user_id' => 1,
        'store_id' => 1,
        'status' => 'confirmed',
        'payment_status' => 'paid',
        'total_amount' => 10,
        'currency' => 'usd',
    ]);

    Payment::query()->create([
        'order_id' => $order->id,
        'stripe_payment_intent_id' => 'pi_dup_1',
        'amount' => 10,
        'currency' => 'USD',
        'status' => 'succeeded',
    ]);

    app(OrderService::class)->handlePaymentSucceeded([
        'id' => 'pi_dup_1',
        'metadata' => ['order_id' => $order->id],
    ]);

    expect(OutboxMessage::query()->count())->toBe(0);
});

it('releases stock and writes outbox when payment fails', function () {
    $order = Order::query()->create([
        'user_id' => 1,
        'store_id' => 1,
        'status' => 'payment_pending',
        'payment_status' => 'pending',
        'total_amount' => 21.0,
        'currency' => 'usd',
    ]);

    Payment::query()->create([
        'order_id' => $order->id,
        'stripe_payment_intent_id' => 'pi_fail_1',
        'stripe_client_secret' => 'secret',
        'amount' => 21.0,
        'currency' => 'USD',
        'status' => 'pending',
    ]);

    app(OrderService::class)->handlePaymentFailed([
        'id' => 'pi_fail_1',
        'metadata' => ['order_id' => $order->id],
    ]);

    $order->refresh();
    expect($order->status)->toBe('payment_failed')
        ->and($order->payment_status)->toBe('failed')
        ->and($order->payment->status)->toBe('failed')
        ->and(OutboxMessage::query()->orderBy('id')->pluck('event_type')->all())
        ->toBe(['stock.release_requested', 'order.payment_failed']);
});
