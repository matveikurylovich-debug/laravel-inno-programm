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
        $this->events->productCreated(
            (int) $product->id,
            (int) $product->store_id,
            (string) $product->name,
            (string) $product->price,
            (bool) $product->is_active,
            $this->searchFields($product),
        );
    }

    public function updated(Product $product): void
    {
        $this->cacheService->forgetProduct($product->id);
        $this->cacheService->forgetProductList($product->store_id);
        $this->events->productUpdated(
            (int) $product->id,
            (int) $product->store_id,
            (string) $product->name,
            (string) $product->price,
            (bool) $product->is_active,
            $this->searchFields($product),
        );
    }

    public function deleted(Product $product): void
    {
        $this->cacheService->forgetProduct($product->id);
        $this->cacheService->forgetProductList($product->store_id);
        $this->events->productDeleted((int) $product->id, (int) $product->store_id);
    }

    /**
     * @return array<string, mixed>
     */
    private function searchFields(Product $product): array
    {
        $product->loadMissing(['category', 'stock']);

        return [
            'description' => (string) ($product->description ?? ''),
            'category_id' => $product->category_id,
            'category_name' => (string) ($product->category->name ?? ''),
            'attributes' => $product->attributes ?? [],
            'quantity' => (int) $product->stock->sum('quantity'),
            'available' => $product->available_stock,
        ];
    }
}
