<?php

declare(strict_types=1);

namespace App\Services\Stock;

use App\Messaging\Producers\CatalogEventsProducer;
use App\Models\Stock;
use App\Services\Products\CatalogCacheService;

class StockAvailability
{
    public function __construct(
        private readonly CatalogEventsProducer $events,
        private readonly CatalogCacheService $cache,
    ) {}

    public function publish(int $productId): void
    {
        $stocks = Stock::query()->where('product_id', $productId)->orderBy('id')->get();

        if ($stocks->isEmpty()) {
            return;
        }

        $storeId = (int) $stocks->first()->store_id;
        $locations = [];

        foreach ($stocks as $stock) {
            $available = max(0, (int) $stock->quantity - (int) $stock->reserved_quantity);
            $locations[] = [
                'location_id' => (int) $stock->location_id,
                'quantity' => (int) $stock->quantity,
                'reserved_quantity' => (int) $stock->reserved_quantity,
                'available' => $available,
            ];
        }

        $available = array_sum(array_column($locations, 'available'));

        $this->cache->forgetProduct($productId);
        $this->cache->forgetProductList($storeId);
        $this->events->stockChanged($productId, $storeId, $available, $locations);
    }
}
