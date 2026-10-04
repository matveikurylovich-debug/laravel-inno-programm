<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\CatalogCacheService;
use App\Services\Kafka\CatalogEventProducer;

class ProductObserver
{
    public function __construct(
        protected CatalogCacheService $cacheService,
        protected CatalogEventProducer $events,
    ) {}

    public function created(Product $product): void
    {
        $this->cacheService->forgetProductList($product->store_id);
        $this->events->productCreated((int) $product->id, (int) $product->store_id);
    }

    public function updated(Product $product): void
    {
        $this->cacheService->forgetProduct($product->id);
        $this->cacheService->forgetProductList($product->store_id);
        $this->events->productUpdated((int) $product->id, (int) $product->store_id);
    }

    public function deleted(Product $product): void
    {
        $this->cacheService->forgetProduct($product->id);
        $this->cacheService->forgetProductList($product->store_id);
        $this->events->productDeleted((int) $product->id, (int) $product->store_id);
    }
}
