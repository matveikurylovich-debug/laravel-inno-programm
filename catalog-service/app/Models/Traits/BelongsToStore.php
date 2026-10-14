<?php

namespace App\Models\Traits;

use App\Models\Store;
use App\Support\CurrentStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToStore
{
    public static function bootBelongsToStore(): void
    {
        static::addGlobalScope('currentStore', function (Builder $query): void {
            $storeId = app(CurrentStore::class)->id();

            if ($storeId === null) {
                return;
            }

            $query->where($query->getModel()->qualifyColumn('store_id'), $storeId);
        });

        static::creating(function ($model): void {
            $storeId = app(CurrentStore::class)->id();

            if ($storeId !== null) {
                $model->store_id = $storeId;
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where($this->qualifyColumn('store_id'), $storeId);
    }
}
