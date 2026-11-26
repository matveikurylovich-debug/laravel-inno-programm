<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Stock;
use App\Services\Products\CatalogCacheService;

class StockObserver
{
    public function __construct(
        private readonly CatalogCacheService $cacheService,
    ) {}

    public function saved(Stock $stock): void
    {
        $this->cacheService->forgetProduct((int) $stock->product_id);
        $this->cacheService->forgetProductList((int) $stock->store_id);
    }
}
