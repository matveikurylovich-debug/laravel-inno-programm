<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToStore
{
    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where($this->qualifyColumn('store_id'), $storeId);
    }
}
