<?php

declare(strict_types=1);

namespace App\Data;

use App\Exceptions\NonRetryableException;
use App\Support\EventPayload;

final readonly class OrderCancelledData
{
    public function __construct(
        public int|string $orderId,
        public string $reason,
        public string $email,
        public int|string|null $userId = null,
        public ?string $eventId = null,
        public ?float $refundedAmount = null,
        public ?string $currency = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $attributes = EventPayload::attributes($payload);
        $reason = $attributes['reason'] ?? null;

        if (! is_string($reason) || trim($reason) === '') {
            throw new NonRetryableException('Поле reason обязательно для события отмены заказа.');
        }

        return new self(
            orderId: EventPayload::requireOrderId($attributes),
            reason: trim($reason),
            email: EventPayload::requireEmail($attributes),
            userId: EventPayload::optionalUserId($attributes),
            eventId: EventPayload::optionalEventId($attributes),
            refundedAmount: EventPayload::optionalAmount($attributes, 'refunded_amount', null),
            currency: EventPayload::optionalCurrency($attributes),
        );
    }
}
