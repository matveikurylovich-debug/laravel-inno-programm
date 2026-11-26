<?php

declare(strict_types=1);

namespace App\Messaging\Handlers\OrderEvents;

use App\Services\Stock\StockReservationService;

class CommitStockOnOrderPaid implements OrderEventHandler
{
    public function __construct(
        private readonly StockReservationService $reservationService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $this->reservationService->commit(
            (string) $payload['order_id'],
            (int) ($payload['store_id'] ?? 0),
        );
    }
}
