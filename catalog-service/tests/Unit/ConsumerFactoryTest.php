<?php

declare(strict_types=1);

use App\Exceptions\NonRetryableException;
use App\Messaging\ConsumerFactory;
use Ecommerce\PlatformKit\Messaging\NonRetryableMessageException;
use Illuminate\Support\Sleep;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\MessageConsumer;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\ConsumedMessage;

function fakeConsumedMessage(): ConsumedMessage
{
    return new ConsumedMessage(
        topicName: 'test.topic',
        partition: 0,
        headers: [],
        body: ['event' => 'order.created'],
        key: 'key-1',
        offset: 1,
        timestamp: 1,
    );
}

it('commits offset manually after transient retry succeeds', function (): void {
    Sleep::fake();
    config([
        'kafka.failed_message_retries' => 2,
        'kafka.failed_message_backoff_ms' => 50,
    ]);

    Kafka::shouldReceiveMessages([fakeConsumedMessage()]);

    $calls = 0;
    $receivedConsumer = null;

    ConsumerFactory::consume('test.topic', 'test-group', 'test.dlq', function (ConsumerMessage $_message, MessageConsumer $consumer) use (&$calls, &$receivedConsumer): void {
        $calls++;
        $receivedConsumer = $consumer;
        if ($calls < 2) {
            throw new RuntimeException('temporary broker glitch');
        }
    });

    expect($calls)->toBe(2)
        ->and($receivedConsumer)->toBeInstanceOf(MessageConsumer::class)
        ->and($receivedConsumer?->consumedMessagesCount())->toBe(1);
    Sleep::assertSleptTimes(1);
});

it('rethrows NonRetryableException immediately without retrying', function (): void {
    Sleep::fake();
    config([
        'kafka.failed_message_retries' => 3,
        'kafka.failed_message_backoff_ms' => 50,
    ]);

    Kafka::shouldReceiveMessages([fakeConsumedMessage()]);

    $calls = 0;

    expect(function () use (&$calls): void {
        ConsumerFactory::consume('test.topic', 'test-group', 'test.dlq', function () use (&$calls): void {
            $calls++;
            throw new NonRetryableException('invalid payload');
        });
    })->toThrow(NonRetryableException::class, 'invalid payload');

    expect($calls)->toBe(1);
    Sleep::assertNeverSlept();
});

it('rethrows NonRetryableMessageException immediately without retrying', function (): void {
    Sleep::fake();
    config([
        'kafka.failed_message_retries' => 3,
        'kafka.failed_message_backoff_ms' => 50,
    ]);

    Kafka::shouldReceiveMessages([fakeConsumedMessage()]);

    $calls = 0;

    expect(function () use (&$calls): void {
        ConsumerFactory::consume('test.topic', 'test-group', 'test.dlq', function () use (&$calls): void {
            $calls++;
            throw new NonRetryableMessageException('poison message');
        });
    })->toThrow(NonRetryableMessageException::class, 'poison message');

    expect($calls)->toBe(1);
    Sleep::assertNeverSlept();
});

it('rethrows transient exception after exhausting configured retries', function (): void {
    Sleep::fake();
    config([
        'kafka.failed_message_retries' => 2,
        'kafka.failed_message_backoff_ms' => 25,
    ]);

    Kafka::shouldReceiveMessages([fakeConsumedMessage()]);

    $calls = 0;

    expect(function () use (&$calls): void {
        ConsumerFactory::consume('test.topic', 'test-group', 'test.dlq', function () use (&$calls): void {
            $calls++;
            throw new RuntimeException('persistent outage');
        });
    })->toThrow(RuntimeException::class, 'persistent outage');

    expect($calls)->toBe(3);
    Sleep::assertSleptTimes(2);
});
