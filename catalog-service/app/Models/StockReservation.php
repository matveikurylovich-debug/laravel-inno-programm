<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReservationStatus;
use App\Models\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ReservationStatus $status
 */
#[Fillable([
    'order_id',
    'store_id',
    'location_id',
    'product_id',
    'quantity',
    'status',
])]
class StockReservation extends Model
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
        ];
    }
}
