<?php

namespace App\Kafka\Consumers\OrderEvents;

use App\Services\Kafka\InventoryEventProducer;
use App\Services\StockReservationService;

class CommitStockOnOrderPaid implements HandlesOrderEvent
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

        if ($this->reservationService->commit($orderId)) {
            $this->producer->emitCommitted($orderId, $storeId);
        }
    }
}
