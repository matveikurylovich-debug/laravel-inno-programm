<?php

declare(strict_types=1);

namespace App\Services\Products;

use App\Messaging\Producers\CatalogEventsProducer;
use App\Models\Product;
use App\Models\Store;

class ProductEventPublisher
{
    public function __construct(
        private readonly CatalogEventsProducer $events,
    ) {}

    public function publishCreated(Product $product): void
    {
        $product->loadMissing(['category', 'stock']);
        $storeActive = (bool) Store::query()->whereKey($product->store_id)->value('is_active');

        $this->events->productCreated(
            (int) $product->id,
            (int) $product->store_id,
            (string) $product->name,
            (string) $product->price,
            (bool) $product->is_active && $storeActive,
            $product->catalogEventExtra(),
        );
    }

    /**
     * @param  list<int>|null  $categoryPath
     */
    public function publishUpdated(Product $product, ?bool $storeActive = null, ?array $categoryPath = null): void
    {
        $product->loadMissing(['category', 'stock']);
        $resolvedStoreActive = $storeActive ?? (bool) Store::query()->whereKey($product->store_id)->value('is_active');

        $this->events->productUpdated(
            (int) $product->id,
            (int) $product->store_id,
            (string) $product->name,
            (string) $product->price,
            (bool) $product->is_active && $resolvedStoreActive,
            $product->catalogEventExtra($categoryPath),
        );
    }

    public function publishDeleted(int $productId, int $storeId): void
    {
        $this->events->productDeleted($productId, $storeId);
    }
}
