<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Messaging;

final readonly class EventEnvelope
{
    public const SCHEMA_VERSION = 1;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $eventId,
        public string $eventType,
        public int $schemaVersion,
        public string $occurredAt,
        public string $producer,
        public array $data,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function wrap(string $eventType, string $producer, array $data, ?string $eventId = null, ?string $occurredAt = null): self
    {
        $id = $eventId ?? (isset($data['event_id']) && is_string($data['event_id']) ? $data['event_id'] : self::uuid());
        $occurred = $occurredAt ?? (isset($data['occurred_at']) && is_string($data['occurred_at']) ? $data['occurred_at'] : gmdate('c'));

        return new self($id, $eventType, self::SCHEMA_VERSION, $occurred, $producer, $data);
    }

    /**
     * @return array{event_id: string, event_type: string, schema_version: int, occurred_at: string, producer: string, data: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_type' => $this->eventType,
            'schema_version' => $this->schemaVersion,
            'occurred_at' => $this->occurredAt,
            'producer' => $this->producer,
            'data' => $this->data,
        ];
    }

    /**
     * @return array{event-type: string, schema-version: string}
     */
    public function headers(): array
    {
        return [
            'event-type' => $this->eventType,
            'schema-version' => (string) $this->schemaVersion,
        ];
    }

    /**
     * Старый плоский payload и новый конверт приводятся к данным события.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public static function flatten(array $body): array
    {
        if (! isset($body['schema_version']) || ! is_array($body['data'] ?? null)) {
            return $body;
        }

        $data = $body['data'];
        $data['event'] = $data['event'] ?? $body['event_type'] ?? null;
        $data['event_id'] = $data['event_id'] ?? $body['event_id'] ?? null;
        $data['occurred_at'] = $data['occurred_at'] ?? $body['occurred_at'] ?? null;

        return $data;
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0F | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3F | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
