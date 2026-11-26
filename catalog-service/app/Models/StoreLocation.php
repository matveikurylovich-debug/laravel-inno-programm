<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[Fillable([
    'store_id',
    'name',
    'city',
    'address',
    'phone',
    'working_hours',
    'is_active',
])]
class StoreLocation extends Model
{
    use BelongsToStore;

    /** @return HasMany<Stock, $this> */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'location_id');
    }

    protected static function booted(): void
    {
        static::deleting(static function (StoreLocation $location): void {
            $blocked = $location->stocks()->exists()
                || StockReservation::query()->where('location_id', $location->id)->exists();

            if ($blocked) {
                throw new HttpException(409, 'У точки есть остатки или резервы');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'store_id' => 'integer',
        ];
    }
}
