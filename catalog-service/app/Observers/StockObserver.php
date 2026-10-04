<?php

namespace App\Observers;

use App\Models\Stock;
use App\Services\CatalogCacheService;
use App\Services\Kafka\CatalogEventProducer;

class StockObserver
{
    public function __construct(
        protected CatalogCacheService $cacheService,
        protected CatalogEventProducer $events,
    ) {}

    public function saved(Stock $stock): void
    {
        $this->cacheService->forgetProduct($stock->product_id);
        $this->cacheService->forgetProductList($stock->store_id);
        $this->events->stockChanged(
            (int) $stock->product_id,
            (int) $stock->store_id,
            (int) $stock->quantity,
            (int) $stock->reserved_quantity,
        );
    }
}
