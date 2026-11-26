<?php

declare(strict_types=1);

namespace App\Messaging\Handlers\OrderEvents;

use App\Services\Stock\StockReservationService;

class ReleaseStockOnOrderCancelled implements OrderEventHandler
{
    public function __construct(
        private readonly StockReservationService $reservationService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $this->reservationService->release((string) $payload['order_id']);
    }
}
