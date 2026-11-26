<?php

declare(strict_types=1);

namespace App\Messaging\Handlers\OrderEvents;

use App\Services\Stock\StockReservationService;

class ReserveStockOnOrderCreated implements OrderEventHandler
{
    public function __construct(
        private readonly StockReservationService $reservationService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $orderId = (string) $payload['order_id'];
        $storeId = (int) ($payload['store_id'] ?? 0);
        $items = $payload['items'] ?? [];

        if (is_object($items)) {
            $items = (array) $items;
        }

        $locationId = isset($payload['location_id']) ? (int) $payload['location_id'] : null;
        $this->reservationService->reserve($orderId, $storeId, $items, $locationId);
    }
}
