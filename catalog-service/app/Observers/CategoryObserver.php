<?php

declare(strict_types=1);

namespace App\Observers;

use App\Messaging\Producers\CatalogEventsProducer;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Services\Products\CatalogCacheService;
use App\Services\Products\ProductEventPublisher;
use Illuminate\Database\Eloquent\Collection;

class CategoryObserver
{
    public function __construct(
        private readonly CatalogCacheService $cacheService,
        private readonly CatalogEventsProducer $events,
        private readonly ProductEventPublisher $productEvents,
    ) {}

    public function created(Category $category): void
    {
        $this->forget($category);
        $this->events->categoryCreated((int) $category->id, (int) $category->store_id, (string) $category->name);
    }

    public function updated(Category $category): void
    {
        $this->forget($category);
        $this->events->categoryUpdated((int) $category->id, (int) $category->store_id, (string) $category->name);

        if ($category->wasChanged('parent_id')) {
            $this->republishSubtree($category);
        }

        if ($category->wasChanged('store_id')) {
            $previousStoreId = $category->getOriginal('store_id');
            if (is_numeric($previousStoreId)) {
                $this->cacheService->forgetCategoryTree((int) $previousStoreId);
                $this->cacheService->forgetProductList((int) $previousStoreId);
            }
        }
    }

    public function deleted(Category $category): void
    {
        $this->forget($category);
        $this->events->categoryDeleted((int) $category->id, (int) $category->store_id, (string) $category->name);
    }

    private function republishSubtree(Category $category): void
    {
        $storeId = (int) $category->store_id;
        $categoryIds = Category::idsIncludingDescendants($storeId, (int) $category->id);
        $paths = Category::pathsById($storeId);
        $storeActive = (bool) Store::query()->whereKey($storeId)->value('is_active');

        Product::query()
            ->where('store_id', $storeId)
            ->whereIn('category_id', $categoryIds)
            ->with(['category', 'stock'])
            ->orderBy('id')
            ->chunkById(100, function (Collection $products) use ($storeActive, $paths): void {
                foreach ($products as $product) {
                    /** @var Product $product */
                    $this->cacheService->forgetProduct((int) $product->id);
                    $this->productEvents->publishUpdated(
                        $product,
                        $storeActive,
                        $paths[(int) $product->category_id] ?? [],
                    );
                }
            });
    }

    private function forget(Category $category): void
    {
        $this->cacheService->forgetCategoryTree((int) $category->store_id);
        $this->cacheService->forgetProductList((int) $category->store_id);
    }
}
