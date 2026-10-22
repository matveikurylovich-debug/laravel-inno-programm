<?php

declare(strict_types=1);

namespace App\Message;

final class CatalogEventMessage
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $eventType,
        public array $payload,
    ) {
    }

    public function getProductId(): int
    {
        $source = isset($this->payload['data']) && is_array($this->payload['data'])
            ? $this->payload['data']
            : $this->payload;

        $id = $source['id'] ?? $source['product_id'] ?? $this->payload['id'] ?? $this->payload['product_id'] ?? 0;

        return (int) $id;
    }
}
