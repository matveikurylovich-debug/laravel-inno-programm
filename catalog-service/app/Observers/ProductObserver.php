<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Product;
use App\Services\Products\CatalogCacheService;
use App\Services\Products\ProductEventPublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductObserver
{
    public function __construct(
        private readonly CatalogCacheService $cacheService,
        private readonly ProductEventPublisher $productEvents,
    ) {}

    public function created(Product $product): void
    {
        $this->cacheService->forgetProductList((int) $product->store_id);
    }

    public function updated(Product $product): void
    {
        $this->cacheService->forgetProduct((int) $product->id);
        $this->cacheService->forgetProductList((int) $product->store_id);
        $this->productEvents->publishUpdated($product);
    }

    public function deleting(Product $product): void
    {
        /** @var list<string> $paths */
        $paths = $product->images()->pluck('path')->all();

        DB::afterCommit(static function () use ($paths): void {
            if ($paths !== []) {
                Storage::disk('minio')->delete($paths);
            }
        });
    }

    public function deleted(Product $product): void
    {
        $this->cacheService->forgetProduct((int) $product->id);
        $this->cacheService->forgetProductList((int) $product->store_id);
        $this->productEvents->publishDeleted((int) $product->id, (int) $product->store_id);
    }
}
