<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OutboxMessage;

class OutboxService
{
    /**
     * Зафиксировать событие в outbox в рамках текущей транзакции БД.
     */
    public function record(
        string $eventType,
        string $aggregateType,
        string $aggregateId,
        array $payload
    ): OutboxMessage {
        return OutboxMessage::create([
            'event_type' => $eventType,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'payload' => $payload,
            'status' => 'pending',
        ]);
    }
}
