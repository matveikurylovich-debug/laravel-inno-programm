<?php

declare(strict_types=1);

use App\Messaging\Producers\AnalyticsEventsProducer;
use App\Models\OutboxMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Junges\Kafka\Contracts\MessageProducer;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Factory;

afterEach(function (): void {
    Date::setTestNow();
});

function pendingOutboxMessage(): OutboxMessage
{
    return OutboxMessage::query()->create([
        'event_type' => 'order.created',
        'aggregate_type' => 'Order',
        'aggregate_id' => (string) Str::uuid(),
        'payload' => ['order_id' => '1'],
        'status' => 'pending',
    ]);
}

function useRealKafka(): void
{
    Kafka::swap(new Factory);
}

function mockOutboxKafka(bool $fail): void
{
    useRealKafka();
    $builder = Mockery::mock(MessageProducer::class);
    $builder->shouldReceive('onTopic')->once()->andReturnSelf();
    $builder->shouldReceive('withMessage')->once()->andReturnSelf();

    if ($fail) {
        $builder->shouldReceive('send')->once()->andThrow(new RuntimeException('broker down'));
    } else {
        $builder->shouldReceive('send')->once()->andReturn(true);
    }

    Kafka::shouldReceive('publish')->once()->andReturn($builder);
}

it('schedules an exponential retry after a broker failure', function (): void {
    Date::setTestNow('2026-10-12 12:00:00');
    $message = pendingOutboxMessage();
    mockOutboxKafka(fail: true);

    $this->artisan('order:relay-outbox', ['--once' => true])->assertSuccessful();

    $message->refresh();
    expect($message->status)->toBe('pending')
        ->and($message->retry_count)->toBe(1)
        ->and($message->error_message)->toBe('broker down')
        ->and($message->next_retry_at->equalTo(now()->addSeconds(2)))->toBeTrue();
});

it('does not publish a message before next_retry_at', function (): void {
    $message = pendingOutboxMessage();
    $message->update([
        'retry_count' => 1,
        'next_retry_at' => now()->addMinute(),
    ]);

    useRealKafka();
    Kafka::shouldReceive('publish')->never();

    $this->artisan('order:relay-outbox', ['--once' => true])->assertSuccessful();

    $message->refresh();
    expect($message->status)->toBe('pending')
        ->and($message->retry_count)->toBe(1);
});

it('publishes a due retry', function (): void {
    $message = pendingOutboxMessage();
    $message->update([
        'retry_count' => 1,
        'next_retry_at' => now()->subSecond(),
        'error_message' => 'broker down',
    ]);

    $this->mock(AnalyticsEventsProducer::class)->shouldReceive('publish')->once();
    mockOutboxKafka(fail: false);

    $this->artisan('order:relay-outbox', ['--once' => true])->assertSuccessful();

    $message->refresh();
    expect($message->status)->toBe('published')
        ->and($message->published_at)->not->toBeNull()
        ->and($message->next_retry_at)->toBeNull()
        ->and($message->error_message)->toBeNull();
});

it('keeps retrying a broker outage past five minutes without a failed status', function (): void {
    Date::setTestNow('2026-10-12 12:00:00');
    $message = pendingOutboxMessage();
    $message->update([
        'retry_count' => 8,
        'next_retry_at' => now()->subSecond(),
    ]);

    mockOutboxKafka(fail: true);

    $this->artisan('order:relay-outbox', ['--once' => true])->assertSuccessful();

    $message->refresh();
    expect($message->status)->toBe('pending')
        ->and($message->retry_count)->toBe(9)
        ->and($message->next_retry_at->equalTo(now()->addSeconds(60)))->toBeTrue();
});

it('publishes two events of one order in id order', function (): void {
    $aggregate = (string) Str::uuid();
    $first = pendingOutboxMessage();
    $first->update(['aggregate_id' => $aggregate, 'event_type' => 'order.created', 'payload' => ['order_id' => $aggregate]]);
    $second = pendingOutboxMessage();
    $second->update(['aggregate_id' => $aggregate, 'event_type' => 'order.cancelled', 'payload' => ['order_id' => $aggregate]]);

    $this->mock(AnalyticsEventsProducer::class)->shouldReceive('publish')->twice();
    mockOutboxKafka(fail: false);
    $this->artisan('order:relay-outbox', ['--once' => true])->assertSuccessful();
    expect($first->fresh()->status)->toBe('published')
        ->and($second->fresh()->status)->toBe('pending');

    mockOutboxKafka(fail: false);
    $this->artisan('order:relay-outbox', ['--once' => true])->assertSuccessful();
    expect($second->fresh()->status)->toBe('published')
        ->and($first->fresh()->published_at->lessThanOrEqualTo($second->fresh()->published_at))->toBeTrue();
});

it('does not let a dead message block a later event of the same order', function (): void {
    $aggregate = (string) Str::uuid();
    $dead = pendingOutboxMessage();
    $dead->update(['aggregate_id' => $aggregate, 'status' => 'dead', 'payload' => ['order_id' => $aggregate]]);
    $next = pendingOutboxMessage();
    $next->update(['aggregate_id' => $aggregate, 'event_type' => 'order.cancelled', 'payload' => ['order_id' => $aggregate]]);

    $this->mock(AnalyticsEventsProducer::class)->shouldReceive('publish')->once();
    mockOutboxKafka(fail: false);
    $this->artisan('order:relay-outbox', ['--once' => true])->assertSuccessful();

    expect($next->fresh()->status)->toBe('published');
});

it('marks an invalid payload dead and can return it with order:retry-outbox', function (): void {
    $message = pendingOutboxMessage();
    $message->update(['payload' => ['store_id' => 1]]);

    useRealKafka();
    Kafka::shouldReceive('publish')->never();
    $this->artisan('order:relay-outbox', ['--once' => true])->assertSuccessful();
    expect($message->fresh()->status)->toBe('dead');

    $this->artisan('order:retry-outbox', ['--failed' => true])->assertSuccessful();
    expect($message->fresh()->status)->toBe('pending');
});

it('reports not ready when the oldest pending outbox row is too old', function (): void {
    config(['orders.outbox_lag_seconds' => 30]);
    $message = pendingOutboxMessage();
    $message->forceFill(['created_at' => now()->subMinutes(5)])->save();

    $this->get('/ready')->assertStatus(503)->assertJsonPath('ready', false);
});

it('does not resend Kafka after RabbitMQ fails and retries the same analytics event', function (): void {
    $message = pendingOutboxMessage();
    mockOutboxKafka(fail: false);
    $this->mock(AnalyticsEventsProducer::class)->shouldReceive('publish')
        ->once()->andThrow(new RuntimeException('RabbitMQ unavailable'));
    $this->artisan('order:relay-outbox', ['--once' => true])->assertSuccessful();
    expect($message->fresh()->kafka_published_at)->not->toBeNull();
    expect($message->fresh()->status)->toBe('pending');

    $message->refresh()->update(['next_retry_at' => now()->subSecond()]);
    useRealKafka();
    Kafka::shouldReceive('publish')->never();
    $this->mock(AnalyticsEventsProducer::class)->shouldReceive('publish')->once()
        ->with('order.created', Mockery::on(fn (array $payload): bool => $payload['event_id'] === 'order-outbox-'.$message->id), true);
    $this->artisan('order:relay-outbox', ['--once' => true])->assertSuccessful();
    expect($message->fresh()->status)->toBe('published');
});

it('forwards refunded_amount on order.cancelled and order.refunded to analytics', function (): void {
    $orderId = (string) Str::uuid();
    $cancelled = OutboxMessage::query()->create([
        'event_type' => 'order.cancelled',
        'aggregate_type' => 'Order',
        'aggregate_id' => $orderId,
        'payload' => [
            'order_id' => $orderId,
            'user_id' => 4,
            'store_id' => 1,
            'total_amount' => 120,
            'refunded_amount' => '0.00',
        ],
        'status' => 'pending',
    ]);
    $refunded = OutboxMessage::query()->create([
        'event_type' => 'order.refunded',
        'aggregate_type' => 'Order',
        'aggregate_id' => $orderId,
        'payload' => [
            'order_id' => $orderId,
            'user_id' => 4,
            'store_id' => 1,
            'refunded_amount' => '120.00',
            'currency' => 'BYN',
        ],
        'status' => 'pending',
    ]);

    $analytics = $this->mock(AnalyticsEventsProducer::class);
    $analytics->shouldReceive('publish')
        ->once()
        ->with('order.cancelled', Mockery::on(fn (array $body): bool => $body['refunded_amount'] === '0.00' && $body['total_amount'] === 120), true);
    $analytics->shouldReceive('publish')
        ->once()
        ->with('order.refunded', Mockery::on(fn (array $body): bool => $body['refunded_amount'] === '120.00' && $body['currency'] === 'BYN' && ! array_key_exists('total_amount', $body)), true);

    mockOutboxKafka(fail: false);
    $this->artisan('order:relay-outbox', ['--once' => true])->assertSuccessful();
    mockOutboxKafka(fail: false);
    $this->artisan('order:relay-outbox', ['--once' => true])->assertSuccessful();

    expect($cancelled->fresh()->status)->toBe('published')
        ->and($refunded->fresh()->status)->toBe('published');
});
