<?php

namespace App\Kafka\Consumers\OrderEvents;

use App\Services\StockReservationService;

class ReleaseStockReservation implements HandlesOrderEvent
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
