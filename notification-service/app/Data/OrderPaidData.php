<?php

declare(strict_types=1);

namespace App\Data;

use App\Support\EventPayload;

final readonly class OrderPaidData
{
    public function __construct(
        public int|string $orderId,
        public float $amount,
        public string $currency,
        public string $email,
        public int|string|null $userId = null,
        public ?string $eventId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $attributes = EventPayload::attributes($payload);

        return new self(
            orderId: EventPayload::requireOrderId($attributes),
            amount: EventPayload::requireAmount($attributes),
            currency: EventPayload::requireCurrency($attributes),
            email: EventPayload::requireEmail($attributes),
            userId: EventPayload::optionalUserId($attributes),
            eventId: EventPayload::optionalEventId($attributes),
        );
    }
}
