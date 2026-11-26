<?php

declare(strict_types=1);

namespace App\Data;

use App\Exceptions\NonRetryableException;
use App\Support\EventPayload;

final readonly class OrderConfirmedData
{
    public function __construct(
        public int|string $orderId,
        public string $email,
        public int|string|null $userId = null,
        public ?string $eventId = null,
        public ?string $estimatedDelivery = null,
        public ?float $amount = null,
        public ?string $currency = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $attributes = EventPayload::attributes($payload);

        $estimatedDelivery = null;
        if (array_key_exists('estimated_delivery', $attributes) && $attributes['estimated_delivery'] !== null) {
            if (! is_string($attributes['estimated_delivery']) || trim($attributes['estimated_delivery']) === '') {
                throw new NonRetryableException('Поле estimated_delivery должно быть непустой строкой.');
            }

            $estimatedDelivery = trim($attributes['estimated_delivery']);
        }

        return new self(
            orderId: EventPayload::requireOrderId($attributes),
            email: EventPayload::requireEmail($attributes),
            userId: EventPayload::optionalUserId($attributes),
            eventId: EventPayload::optionalEventId($attributes),
            estimatedDelivery: $estimatedDelivery,
            amount: EventPayload::optionalAmount($attributes),
            currency: EventPayload::optionalCurrency($attributes),
        );
    }
}
