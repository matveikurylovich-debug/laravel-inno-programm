<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Analytics;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;
use Throwable;

/**
 * Одно долгоживущее соединение с подтверждениями брокера.
 * Очередь консьюмера не объявляется: её создаёт definitions.json.
 */
final class AnalyticsPublisher
{
    private ?ConfirmingChannel $live = null;

    public function __construct(
        private readonly string $url,
        private readonly string $exchange,
        private readonly ?ConfirmingChannel $channel = null,
    ) {}

    /**
     * @param  array<string, mixed>  $body
     */
    public function publish(string $event, array $body, bool $strict = false): void
    {
        if ($this->channel === null && $this->url === '') {
            if ($strict) {
                throw new RuntimeException('Analytics broker is not configured');
            }

            return;
        }

        try {
            $this->open()->publish($this->encode($event, $body), $this->exchange, $event);
        } catch (Throwable $exception) {
            $this->live = null;
            throw $exception;
        }
    }

    private function open(): ConfirmingChannel
    {
        if ($this->channel instanceof ConfirmingChannel) {
            return $this->channel;
        }

        return $this->live ??= $this->connect();
    }

    private function connect(): ConfirmingChannel
    {
        $parts = parse_url($this->url);
        if (! is_array($parts)) {
            throw new RuntimeException('Analytics broker URL is invalid');
        }

        $vhost = rawurldecode(ltrim((string) ($parts['path'] ?? '/'), '/'));
        if ($vhost === '') {
            $vhost = '/';
        }

        $connection = new AMQPStreamConnection(
            (string) ($parts['host'] ?? 'analytics-rabbitmq'),
            (int) ($parts['port'] ?? 5672),
            rawurldecode((string) ($parts['user'] ?? 'guest')),
            rawurldecode((string) ($parts['pass'] ?? 'guest')),
            $vhost,
        );
        $channel = $connection->channel();
        $channel->confirm_select();

        return new AmqpConfirmingChannel($connection, $channel);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function encode(string $event, array $body): string
    {
        $encoded = json_encode(array_merge(['event' => $event], $body), JSON_THROW_ON_ERROR);

        return is_string($encoded) ? $encoded : '{}';
    }
}

final class AmqpConfirmingChannel implements ConfirmingChannel
{
    private ?RuntimeException $rejected = null;

    public function __construct(
        private readonly AMQPStreamConnection $connection,
        private readonly AMQPChannel $channel,
    ) {
        $channel->set_nack_handler(function (): void {
            $this->rejected = new RuntimeException('Analytics event nacked');
        });
        $channel->set_return_listener(function (): void {
            $this->rejected = new RuntimeException('Analytics event returned');
        });
    }

    public function publish(string $body, string $exchange, string $routingKey): void
    {
        if (! $this->connection->isConnected()) {
            throw new RuntimeException('Analytics broker connection is closed');
        }
        $this->rejected = null;
        $this->channel->basic_publish(
            new AMQPMessage($body, [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ]),
            $exchange,
            $routingKey,
            true,
        );
        $this->channel->wait_for_pending_acks_returns(5);
        if ($this->rejected instanceof RuntimeException) {
            throw $this->rejected;
        }
    }
}
