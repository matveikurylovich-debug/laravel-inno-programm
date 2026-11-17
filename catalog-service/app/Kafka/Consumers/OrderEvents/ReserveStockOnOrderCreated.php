<?php

namespace App\Kafka\Consumers\OrderEvents;

use App\Services\Kafka\InventoryEventProducer;
use App\Services\StockReservationService;

class ReserveStockOnOrderCreated implements HandlesOrderEvent
{
    public function __construct(
        private readonly StockReservationService $reservationService,
        private readonly InventoryEventProducer $producer,
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
        $reserved = $this->reservationService->reserve($orderId, $storeId, $items, $locationId);

        if ($reserved) {
            $this->producer->emitReserved($orderId, $storeId);

            return;
        }

        $this->producer->emitFailed($orderId, $storeId, 'Insufficient stock');
    }
}
