<?php

declare(strict_types=1);

namespace App\Messaging\Messages;

use Carbon\CarbonImmutable;

final readonly class UserRegisteredMessage
{
    public function __construct(
        public string $eventId,
        public int $userId,
        public string $email,
        public string $name,
        public CarbonImmutable $occurredAt,
    ) {}

    /**
     * @return array{event_id: string, user_id: int, occurred_at: string}
     */
    public function toAnalytics(): array
    {
        return [
            'event_id' => $this->eventId,
            'user_id' => $this->userId,
            'occurred_at' => $this->occurredAt->toIso8601String(),
        ];
    }
}
