<?php

declare(strict_types=1);

use App\Models\OutboxMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Junges\Kafka\Contracts\MessageProducer;
use Junges\Kafka\Facades\Kafka;

afterEach(function () {
    Carbon::setTestNow();
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

function mockOutboxKafka(bool $fail): void
{
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

it('schedules an exponential retry after a broker failure', function () {
    Carbon::setTestNow('2026-10-12 12:00:00');
    $message = pendingOutboxMessage();
    mockOutboxKafka(fail: true);

    $this->artisan('order:outbox-relay', ['--once' => true])->assertSuccessful();

    $message->refresh();
    expect($message->status)->toBe('pending')
        ->and($message->retry_count)->toBe(1)
        ->and($message->error_message)->toBe('broker down')
        ->and($message->next_retry_at->equalTo(now()->addSeconds(2)))->toBeTrue();
});

it('does not publish a message before next_retry_at', function () {
    $message = pendingOutboxMessage();
    $message->update([
        'retry_count' => 1,
        'next_retry_at' => now()->addMinute(),
    ]);

    Kafka::shouldReceive('publish')->never();

    $this->artisan('order:outbox-relay', ['--once' => true])->assertSuccessful();

    $message->refresh();
    expect($message->status)->toBe('pending')
        ->and($message->retry_count)->toBe(1);
});

it('publishes a due retry', function () {
    $message = pendingOutboxMessage();
    $message->update([
        'retry_count' => 1,
        'next_retry_at' => now()->subSecond(),
        'error_message' => 'broker down',
    ]);

    mockOutboxKafka(fail: false);

    $this->artisan('order:outbox-relay', ['--once' => true])->assertSuccessful();

    $message->refresh();
    expect($message->status)->toBe('published')
        ->and($message->published_at)->not->toBeNull()
        ->and($message->next_retry_at)->toBeNull()
        ->and($message->error_message)->toBeNull();
});

it('marks a message failed after the retry limit', function () {
    $message = pendingOutboxMessage();
    $message->update([
        'retry_count' => 4,
        'next_retry_at' => now()->subSecond(),
    ]);

    mockOutboxKafka(fail: true);

    $this->artisan('order:outbox-relay', ['--once' => true])->assertSuccessful();

    $message->refresh();
    expect($message->status)->toBe('failed')
        ->and($message->retry_count)->toBe(5)
        ->and($message->next_retry_at)->toBeNull();
});
