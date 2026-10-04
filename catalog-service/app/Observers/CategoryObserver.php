<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\CatalogCacheService;
use App\Services\Kafka\CatalogEventProducer;

class CategoryObserver
{
    public function __construct(
        protected CatalogCacheService $cacheService,
        protected CatalogEventProducer $events,
    ) {}

    public function created(Category $category): void
    {
        $this->forget($category);
        $this->events->categoryCreated((int) $category->id, (int) $category->store_id);
    }

    public function updated(Category $category): void
    {
        $this->forget($category);
        $this->events->categoryUpdated((int) $category->id, (int) $category->store_id);

        if ($category->wasChanged('store_id')) {
            $previousStoreId = $category->getOriginal('store_id');
            $this->cacheService->forgetCategoryTree($previousStoreId);
            $this->cacheService->forgetProductList($previousStoreId);
        }
    }

    public function deleted(Category $category): void
    {
        $this->forget($category);
        $this->events->categoryDeleted((int) $category->id, (int) $category->store_id);
    }

    private function forget(Category $category): void
    {
        $this->cacheService->forgetCategoryTree($category->store_id);
        $this->cacheService->forgetProductList($category->store_id);
    }
}
