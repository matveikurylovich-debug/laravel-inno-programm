<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Tests;

use Ecommerce\PlatformKit\Analytics\AnalyticsPublisher;
use Ecommerce\PlatformKit\Analytics\ConfirmingChannel;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AnalyticsPublisherTest extends TestCase
{
    public function test_broker_nack_is_an_exception(): void
    {
        $channel = new class implements ConfirmingChannel
        {
            public function publish(string $body, string $exchange, string $routingKey): void
            {
                throw new RuntimeException('Analytics event nacked');
            }
        };

        $publisher = new AnalyticsPublisher('amqp://app:secret@analytics-rabbitmq:5672/', 'analytics.events', $channel);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Analytics event nacked');
        $publisher->publish('user.registered', ['event_id' => 'e1'], true);
    }

    public function test_missing_broker_is_ignored_until_the_call_is_strict(): void
    {
        $publisher = new AnalyticsPublisher('', 'analytics.events');
        $publisher->publish('user.registered', ['event_id' => 'e1']);

        $this->expectException(RuntimeException::class);
        $publisher->publish('user.registered', ['event_id' => 'e1'], true);
    }
}
