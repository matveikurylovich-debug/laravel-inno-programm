<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class CatalogCacheService
{
    // Время жизни кэша в секундах
    public const TTL_CATEGORY_TREE = 86400; // 24 часа
    public const TTL_PRODUCT_CARD  = 3600;  // 1 час
    public const TTL_PRODUCT_LIST  = 300;   // 5 минут

    /**
     * Генерация ключа для дерева категорий магазина
     */
    public static function categoryTreeKey(int|string $storeId): string
    {
        return "catalog:store:{$storeId}:categories:tree";
    }

    /**
     * Генерация ключа для карточки товара
     */
    public static function productCardKey(int|string $productId): string
    {
        return "catalog:product:{$productId}";
    }

    /**
     * Получить дерево категорий из Redis (или собрать из БД и закэшировать)
     */
    public function getCategoryTree(int|string $storeId): array
    {
        $key = self::categoryTreeKey($storeId);

        return Cache::remember($key, self::TTL_CATEGORY_TREE, function () use ($storeId) {
            return Category::forStore($storeId)
                ->whereNull('parent_id')
                ->with('allChildren')
                ->orderBy('position')
                ->get()
                ->toArray();
        });
    }

    /**
     * Сбросить кэш дерева категорий магазина
     */
    public function forgetCategoryTree(int|string $storeId): bool
    {
        return Cache::forget(self::categoryTreeKey($storeId));
    }

    /**
     * Получить карточку товара из Redis (или собрать из БД и закэшировать)
     */
    public function getProduct(int|string $productId): ?array
    {
        $key = self::productCardKey($productId);

        return Cache::remember($key, self::TTL_PRODUCT_CARD, function () use ($productId) {
            $product = Product::with(['images', 'stock', 'category'])
                ->find($productId);

            if (!$product) {
                return null;
            }

            $data = $product->toArray();
            // Добавляем виртуальное поле доступного остатка
            $data['available_stock'] = $product->available_stock ?? ($product->stock ? ($product->stock->quantity - $product->stock->reserved_quantity) : 0);

            return $data;
        });
    }

    /**
     * Сбросить кэш карточки товара
     */
    public function forgetProduct(int|string $productId): bool
    {
        return Cache::forget(self::productCardKey($productId));
    }

    public static function productListVersionKey(int|string $storeId): string
    {
        return "catalog:store:{$storeId}:products:version";
    }

    /**
     * Листинг товаров. Версия ключа сбрасывается при изменении каталога магазина.
     */
    public function rememberProductList(int|string $storeId, string $fingerprint, callable $resolver): array
    {
        $version = (int) Cache::get(self::productListVersionKey($storeId), 1);
        $key = "catalog:store:{$storeId}:product-list:v{$version}:{$fingerprint}";

        return Cache::remember($key, self::TTL_PRODUCT_LIST, $resolver);
    }

    public function forgetProductList(int|string $storeId): void
    {
        $key = self::productListVersionKey($storeId);
        Cache::forever($key, ((int) Cache::get($key, 1)) + 1);
    }
}