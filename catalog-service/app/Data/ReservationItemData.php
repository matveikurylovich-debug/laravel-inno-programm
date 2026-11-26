<?php

declare(strict_types=1);

namespace App\Data;

final readonly class ReservationItemData
{
    public function __construct(
        public int $productId,
        public int $quantity,
    ) {}

    /**
     * @param  array{product_id: int|string, quantity: int|string}  $item
     */
    public static function fromArray(array $item): self
    {
        return new self((int) $item['product_id'], (int) $item['quantity']);
    }
}
