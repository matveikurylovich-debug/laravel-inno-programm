<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Override;

class ProductSnapshot extends Model
{
    #[Override]
    protected $primaryKey = 'product_id';

    #[Override]
    public $incrementing = false;

    #[Override]
    protected $keyType = 'int';

    #[Override]
    protected $fillable = [
        'product_id',
        'store_id',
        'name',
        'price',
        'is_active',
    ];

    #[Override]
    protected $casts = [
        'price' => 'float',
        'is_active' => 'boolean',
        'store_id' => 'integer',
    ];
}
