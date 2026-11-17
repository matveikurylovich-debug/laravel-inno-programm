<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'store_id',
        'category_id',
        'sku',
        'name',
        'slug',
        'description',
        'price',
        'is_active',
        'attributes',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'attributes' => 'array',
    ];

    protected $appends = [
        'available_stock',
    ];

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
     * Виртуальный атрибут доступного остатка: физический минус зарезервированный.
     */
    public function getAvailableStockAttribute(): int
    {
        if (! $this->relationLoaded('stock') || ! $this->stock->count()) {
            return 0;
        }

        return $this->stock->sum(function (Stock $stock) {
            return max(0, $stock->quantity - $stock->reserved_quantity);
        });
    }
}
