<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class AnalyticsEventPublisher
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function publish(string $event, array $body, bool $strict = false): void
    {
        $url = (string) config('services.analytics_broker.url');
        if ($url === '') {
            if ($strict) {
                throw new \RuntimeException('Analytics broker is not configured');
            }

            return;
        }

        try {
            $parts = parse_url($url) ?: [];
            $connection = new AMQPStreamConnection(
                (string) ($parts['host'] ?? 'analytics-rabbitmq'),
                (int) ($parts['port'] ?? 5672),
                (string) ($parts['user'] ?? 'guest'),
                (string) ($parts['pass'] ?? 'guest'),
            );
            $channel = $connection->channel();
            $exchange = (string) config('services.analytics_broker.exchange', 'analytics.events');
            $channel->exchange_declare($exchange, 'topic', false, true, false);
            $channel->queue_declare('analytics.events', false, true, false, false);
            foreach (['user.registered', 'order.created', 'order.paid', 'order.cancelled', 'search.performed'] as $routingKey) {
                $channel->queue_bind('analytics.events', $exchange, $routingKey);
            }
            $channel->confirm_select();
            $channel->basic_publish(
                new AMQPMessage(
                    json_encode(array_merge(['event' => $event], $body), JSON_THROW_ON_ERROR),
                    ['content_type' => 'application/json', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT],
                ),
                $exchange,
                $event,
            );
            $channel->wait_for_pending_acks_returns(5);
            $channel->close();
            $connection->close();
        } catch (Throwable $e) {
            if ($strict) {
                throw $e;
            }
            Log::warning('Не удалось опубликовать событие аналитики: '.$e->getMessage(), ['event' => $event]);
        }
    }
}
