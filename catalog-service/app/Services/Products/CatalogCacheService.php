<?php

declare(strict_types=1);

namespace App\Services\Products;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CatalogCacheService
{
    public const TTL_CATEGORY_TREE = 86400;

    public const TTL_PRODUCT_CARD = 3600;

    public const TTL_PRODUCT_LIST = 300;

    public static function categoryTreeKey(int $storeId): string
    {
        return "catalog:store:{$storeId}:categories:tree";
    }

    public static function productCardKey(int $productId): string
    {
        return "catalog:product:{$productId}";
    }

    public static function productListVersionKey(int $storeId): string
    {
        return "catalog:store:{$storeId}:products:version";
    }

    /**
     * @param  array{category_id?: int|null, page?: int, per_page?: int}  $filters
     */
    public static function productListFingerprint(array $filters): string
    {
        $categoryId = isset($filters['category_id']) ? (int) $filters['category_id'] : null;
        $page = isset($filters['page']) ? (int) $filters['page'] : 1;
        $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : 20;

        return sprintf(
            'cat:%s:p:%d:pp:%d',
            $categoryId !== null ? (string) $categoryId : 'all',
            $page,
            $perPage,
        );
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getCategoryTree(int $storeId): array
    {
        $key = self::categoryTreeKey($storeId);

        /** @var array<int|string, mixed> $tree */
        $tree = Cache::remember($key, self::TTL_CATEGORY_TREE, function () use ($storeId): array {
            $categories = Category::query()
                ->forStore($storeId)
                ->whereNull('parent_id')
                ->with('allChildren')
                ->orderBy('position')
                ->get();

            $payload = CategoryResource::collection($categories)->response()->getData(true);
            $data = is_array($payload) ? ($payload['data'] ?? []) : [];

            return is_array($data) ? $data : [];
        });

        return $tree;
    }

    public function forgetCategoryTree(int $storeId): void
    {
        $key = self::categoryTreeKey($storeId);

        DB::afterCommit(static function () use ($key): void {
            Cache::forget($key);
        });
    }

    /**
     * @param  Closure(): (array<string, mixed>|null)  $resolver
     * @return array<string, mixed>|null
     */
    public function rememberProduct(int $productId, Closure $resolver): ?array
    {
        $key = self::productCardKey($productId);
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        $resolved = $resolver();

        if ($resolved !== null) {
            Cache::put($key, $resolved, self::TTL_PRODUCT_CARD);
        }

        return $resolved;
    }

    public function forgetProduct(int $productId): void
    {
        $key = self::productCardKey($productId);

        DB::afterCommit(static function () use ($key): void {
            Cache::forget($key);
        });
    }

    /**
     * @param  array{category_id?: int|null, page?: int, per_page?: int}  $filters
     * @param  Closure(): array<string, mixed>  $resolver
     * @return array<string, mixed>
     */
    public function rememberProductList(int $storeId, array $filters, Closure $resolver): array
    {
        $rawVersion = Cache::get(self::productListVersionKey($storeId), 1);
        $version = is_numeric($rawVersion) ? (int) $rawVersion : 1;
        $fingerprint = self::productListFingerprint($filters);
        $key = "catalog:store:{$storeId}:product-list:v{$version}:{$fingerprint}";

        /** @var array<string, mixed> $payload */
        $payload = Cache::remember($key, self::TTL_PRODUCT_LIST, $resolver);

        return $payload;
    }

    public function forgetProductList(int $storeId): void
    {
        $key = self::productListVersionKey($storeId);

        DB::afterCommit(static function () use ($key): void {
            Cache::add($key, 1);
            Cache::increment($key);
        });
    }
}
