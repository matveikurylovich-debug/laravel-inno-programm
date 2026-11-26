<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Messaging;

abstract class EventRouter
{
    /**
     * Map of event_type => callable(array<string, mixed>, EventEnvelope): void.
     *
     * @return array<string, callable(array<string, mixed>, EventEnvelope): void>
     */
    abstract protected function routes(): array;

    /**
     * @param  array<string, mixed>  $rawBody
     * @param  array<string, mixed>  $headers
     */
    public function dispatch(array $rawBody, array $headers = []): bool
    {
        $flattened = EventEnvelope::flatten($rawBody);
        $eventType = $rawBody['event_type']
            ?? $flattened['event']
            ?? $headers['event-type']
            ?? null;

        if (! is_string($eventType) || $eventType === '') {
            throw new NonRetryableMessageException('Kafka message is missing event_type.');
        }

        $routes = $this->routes();
        if (! isset($routes[$eventType])) {
            return false;
        }

        $envelope = EventEnvelope::wrap(
            eventType: $eventType,
            producer: isset($rawBody['producer']) && is_string($rawBody['producer']) ? $rawBody['producer'] : 'unknown',
            data: $flattened,
            eventId: isset($flattened['event_id']) && is_string($flattened['event_id']) ? $flattened['event_id'] : null,
            occurredAt: isset($flattened['occurred_at']) && is_string($flattened['occurred_at']) ? $flattened['occurred_at'] : null,
        );

        ($routes[$eventType])($flattened, $envelope);

        return true;
    }
}
