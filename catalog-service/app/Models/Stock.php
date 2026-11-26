<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $available_stock
 */
#[Fillable([
    'store_id',
    'location_id',
    'product_id',
    'quantity',
    'reserved_quantity',
])]
class Stock extends Model
{
    use BelongsToStore;

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<StoreLocation, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(StoreLocation::class, 'location_id');
    }

    /**
     * @return Attribute<int, never>
     */
    protected function availableStock(): Attribute
    {
        return Attribute::make(get: fn (): int => max(0, (int) $this->quantity - (int) $this->reserved_quantity));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'reserved_quantity' => 'integer',
        ];
    }
}
