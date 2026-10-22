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
    public function publish(string $event, array $body): void
    {
        $url = (string) config('services.analytics_broker.url');
        if ($url === '') {
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
            $channel->basic_publish(
                new AMQPMessage(
                    json_encode(array_merge(['event' => $event], $body), JSON_THROW_ON_ERROR),
                    ['content_type' => 'application/json', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT],
                ),
                $exchange,
                $event,
            );
            $channel->close();
            $connection->close();
        } catch (Throwable $e) {
            Log::warning('Не удалось опубликовать событие аналитики: '.$e->getMessage(), ['event' => $event]);
        }
    }
}
