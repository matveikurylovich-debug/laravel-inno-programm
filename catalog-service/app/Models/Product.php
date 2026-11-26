<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\HasSlug;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Override;

/**
 * @property-read int $available_stock
 */
#[Appends([
    'available_stock',
])]
#[Fillable([
    'store_id',
    'category_id',
    'sku',
    'name',
    'slug',
    'description',
    'price',
    'is_active',
    'attributes',
])]
class Product extends Model
{
    use BelongsToStore;
    use HasSlug;

    #[Override]
    protected $attributes = [
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        static::creating(static function (Product $product): void {
            if ($product->getAttribute('attributes') === null) {
                $product->setAttribute('attributes', []);
            }
        });
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<ProductImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    /** @return HasOne<ProductImage, $this> */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    /** @return HasMany<Stock, $this> */
    public function stock(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereHas('store', static fn (Builder $store): Builder => $store->where('is_active', true));
    }

    /**
     * @param  Builder<static>  $query
     * @param  array{store_id: int, category_id?: int|null}  $filters
     * @return Builder<static>
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): Builder
    {
        if (! empty($filters['category_id'])) {
            $query->whereIn(
                'category_id',
                Category::idsIncludingDescendants($filters['store_id'], (int) $filters['category_id']),
            );
        }

        return $query->latest();
    }

    /**
     * @param  list<int>|null  $categoryPath
     * @return array<string, mixed>
     */
    public function catalogEventExtra(?array $categoryPath = null): array
    {
        $this->loadMissing(['category', 'stock']);

        return [
            'description' => (string) ($this->description ?? ''),
            'category_id' => $this->category_id,
            'category_name' => (string) ($this->category->name ?? ''),
            'category_path' => $categoryPath ?? $this->categoryPath(),
            'attributes' => $this->getAttribute('attributes') ?? [],
            'quantity' => (int) $this->stock->sum('quantity'),
            'available' => $this->available_stock,
        ];
    }

    /**
     * @return list<int>
     */
    public function categoryPath(): array
    {
        if ($this->category_id === null) {
            return [];
        }

        $this->loadMissing('category');

        if ($this->category === null) {
            return [(int) $this->category_id];
        }

        return $this->category->pathIds();
    }

    /**
     * @return Attribute<int, never>
     */
    protected function availableStock(): Attribute
    {
        return Attribute::make(get: function (): int {
            if (! $this->relationLoaded('stock') || $this->stock->isEmpty()) {
                return 0;
            }

            return (int) $this->stock->sum(static fn (Stock $stock): int => max(0, $stock->quantity - $stock->reserved_quantity));
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'attributes' => 'array',
        ];
    }
}
