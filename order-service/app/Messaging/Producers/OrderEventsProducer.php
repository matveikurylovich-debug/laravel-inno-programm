<?php

declare(strict_types=1);

namespace App\Messaging\Producers;

use App\Models\OutboxMessage;
use Ecommerce\PlatformKit\Messaging\EventEnvelope;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\Message;

class OrderEventsProducer
{
    public function publish(OutboxMessage $message): void
    {
        $commands = ['stock.commit_requested', 'stock.release_requested', 'stock.reserve_requested'];
        $topic = in_array($message->event_type, $commands, true)
            ? (string) config('kafka.topics.stock_commands', 'stock.commands')
            : (string) config('kafka.topics.order_events', 'order.events');
        $payload = array_merge($message->payload, ['event' => $message->event_type]);
        $envelope = EventEnvelope::wrap($message->event_type, 'order-service', $payload);

        Kafka::publish()
            ->onTopic($topic)
            ->withMessage(new Message(
                headers: array_merge(['source' => 'order-service'], $envelope->headers()),
                body: $envelope->toArray(),
                key: (string) $message->aggregate_id,
            ))
            ->send();
    }
}
