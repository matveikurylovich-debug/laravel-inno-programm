<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\HasSlug;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'store_id',
    'parent_id',
    'name',
    'slug',
    'position',
])]
class Category extends Model
{
    use BelongsToStore;
    use HasSlug;

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('position');
    }

    /** @return HasMany<Category, $this> */
    public function allChildren(): HasMany
    {
        return $this->children()->with('allChildren');
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function root(Builder $query): Builder
    {
        return $query->whereNull('parent_id')->orderBy('position');
    }

    /**
     * @return list<int>
     */
    public static function idsIncludingDescendants(int $storeId, int $categoryId): array
    {
        $childrenByParent = [];

        foreach (static::query()->where('store_id', $storeId)->get(['id', 'parent_id']) as $category) {
            $childrenByParent[$category->parent_id === null ? 0 : (int) $category->parent_id][] = (int) $category->id;
        }

        $ids = [];
        $stack = [$categoryId];

        while ($stack !== []) {
            $current = array_pop($stack);

            if (in_array($current, $ids, true)) {
                continue;
            }

            $ids[] = $current;

            foreach ($childrenByParent[$current] ?? [] as $childId) {
                $stack[] = $childId;
            }
        }

        return $ids;
    }

    /**
     * @return list<int>
     */
    public function categoryPath(): array
    {
        return $this->pathIds();
    }

    /**
     * @return list<int>
     */
    public function pathIds(): array
    {
        $paths = self::pathsById((int) $this->store_id);

        return $paths[(int) $this->id] ?? [(int) $this->id];
    }

    /**
     * @return array<int, list<int>>
     */
    public static function pathsById(int $storeId): array
    {
        $parents = [];

        foreach (static::query()->where('store_id', $storeId)->get(['id', 'parent_id']) as $category) {
            $parents[(int) $category->id] = $category->parent_id === null ? null : (int) $category->parent_id;
        }

        $paths = [];

        foreach (array_keys($parents) as $id) {
            $paths[(int) $id] = self::pathFor((int) $id, $parents);
        }

        return $paths;
    }

    /**
     * @param  array<int, int|null>  $parents
     * @return list<int>
     */
    private static function pathFor(int $id, array $parents): array
    {
        $path = [];
        $seen = [];
        $current = $id;

        while (! isset($seen[$current])) {
            $seen[$current] = true;
            $path[] = $current;
            $parent = $parents[$current] ?? null;

            if ($parent === null || ! array_key_exists($parent, $parents)) {
                break;
            }

            $current = $parent;
        }

        return array_reverse($path);
    }
}
