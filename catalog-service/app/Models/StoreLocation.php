<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreLocation extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'store_id',
        'name',
        'city',
        'address',
        'phone',
        'working_hours',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'store_id' => 'integer',
    ];

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'location_id');
    }
}
