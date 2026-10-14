<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Redis;

class Order extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'email',
        'store_id',
        'status',
        'payment_status',
        'total_amount',
        'currency',
        'cancel_reason',
    ];

    protected $casts = [
        'total_amount' => 'float',
        'user_id' => 'integer',
        'store_id' => 'integer',
    ];

    // Позиции заказа (товары)
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // Связанный платеж Stripe
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * Контракт Notification Service: email снаружи, поля заказа внутри data.
     * order_id и store_id остаются на верхнем уровне для Catalog Service.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function notificationEnvelope(array $data = []): array
    {
        return [
            'email' => $this->email,
            'user_id' => $this->user_id,
            'order_id' => $this->id,
            'store_id' => $this->store_id,
            'total_amount' => $this->total_amount,
            'data' => array_merge([
                'order_id' => $this->id,
                'total_amount' => $this->total_amount,
            ], $data),
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (Order $order): void {
            if ($order->wasChanged('status')) {
                Redis::setex("order:saga:{$order->id}", 86400, (string) $order->status);
            }
        });
    }
}
