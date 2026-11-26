<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Product;
use App\Models\Store;
use App\Services\Products\CatalogCacheService;
use App\Services\Products\ProductEventPublisher;
use Illuminate\Database\Eloquent\Collection;

class StoreObserver
{
    public function __construct(
        private readonly CatalogCacheService $cacheService,
        private readonly ProductEventPublisher $productEvents,
    ) {}

    public function updated(Store $store): void
    {
        if (! $store->wasChanged('is_active')) {
            return;
        }

        $this->cacheService->forgetProductList((int) $store->id);

        Product::query()
            ->where('store_id', $store->id)
            ->with(['category', 'stock'])
            ->orderBy('id')
            ->chunkById(100, function (Collection $products) use ($store): void {
                foreach ($products as $product) {
                    /** @var Product $product */
                    $this->cacheService->forgetProduct((int) $product->id);
                    $this->productEvents->publishUpdated($product, (bool) $store->is_active);
                }
            });
    }
}
