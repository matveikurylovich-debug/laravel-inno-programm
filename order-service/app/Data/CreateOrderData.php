<?php

declare(strict_types=1);

namespace App\Data;

final readonly class CreateOrderData
{
    /**
     * @param  list<array{product_id: int, quantity: int}>  $lines
     */
    public function __construct(
        public int $userId,
        public string $email,
        public int $storeId,
        public int $locationId,
        public array $lines,
    ) {}
}
