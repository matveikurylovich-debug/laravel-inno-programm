<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'store_id',
        'parent_id',
        'name',
        'slug',
        'position',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('position');
    }

    /**
     * Рекурсивная загрузка всех потомков для построения дерева.
     */
    public function allChildren(): HasMany
    {
        return $this->children()->with('allChildren');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Scope: только корневые категории магазина.
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id')->orderBy('position');
    }

    /**
     * Идентификатор категории и всех её потомков внутри магазина.
     *
     * @return list<int>
     */
    public static function idsIncludingDescendants(int $storeId, int $categoryId): array
    {
        $childrenByParent = [];

        foreach (static::query()->where('store_id', $storeId)->get(['id', 'parent_id']) as $category) {
            $childrenByParent[$category->parent_id ?? 0][] = $category->id;
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
}
