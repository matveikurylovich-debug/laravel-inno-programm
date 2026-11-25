<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'stripe_payment_intent_id',
        'stripe_client_secret',
        'amount',
        'currency',
        'status',
        'raw_response',
    ];

    protected $hidden = [
        'raw_response',
    ];

    protected $appends = [
        'publishable_key',
    ];

    protected $casts = [
        'amount' => 'float',
        'raw_response' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getPublishableKeyAttribute(): ?string
    {
        $key = config('stripe.publishable');

        return is_string($key) && $key !== '' ? $key : null;
    }
}
