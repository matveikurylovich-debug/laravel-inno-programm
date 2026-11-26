<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Override;

/**
 * @property string $id
 * @property int $user_id
 * @property string|null $email
 * @property int $store_id
 * @property int|null $location_id
 * @property OrderStatus $status
 * @property PaymentStatus $payment_status
 * @property string $total_amount
 * @property string $currency
 * @property string|null $cancel_reason
 * @property Carbon|null $payment_expires_at
 * @property Carbon|null $reserve_deadline
 * @property Carbon|null $reserve_republished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Order extends Model
{
    use HasUuids;

    #[Override]
    protected $fillable = [
        'user_id',
        'email',
        'store_id',
        'location_id',
        'status',
        'payment_status',
        'total_amount',
        'currency',
        'cancel_reason',
        'payment_expires_at',
        'reserve_deadline',
        'reserve_republished_at',
    ];

    #[Override]
    protected $hidden = [
        'payment_expires_at',
        'reserve_deadline',
        'reserve_republished_at',
    ];

    #[Override]
    protected $casts = [
        'total_amount' => 'decimal:2',
        'user_id' => 'integer',
        'store_id' => 'integer',
        'location_id' => 'integer',
        'status' => OrderStatus::class,
        'payment_status' => PaymentStatus::class,
        'payment_expires_at' => 'datetime',
        'reserve_deadline' => 'datetime',
        'reserve_republished_at' => 'datetime',
    ];

    /**
     * @return array<string, list<OrderStatus>>
     */
    public static function transitions(): array
    {
        return [
            OrderStatus::Created->value => [OrderStatus::StockReserved, OrderStatus::Cancelled],
            OrderStatus::StockReserved->value => [OrderStatus::PaymentPending, OrderStatus::Cancelled],
            OrderStatus::PaymentPending->value => [OrderStatus::Paid, OrderStatus::Cancelled],
            OrderStatus::Paid->value => [OrderStatus::Confirmed, OrderStatus::Cancelled],
            OrderStatus::Confirmed->value => [OrderStatus::Cancelled],
            OrderStatus::Cancelled->value => [],
        ];
    }

    public function transitionTo(OrderStatus $target): void
    {
        $current = $this->status;
        if ($current === $target) {
            return;
        }

        $allowed = self::transitions()[$current->value] ?? [];
        if (! in_array($target, $allowed, true)) {
            throw new InvalidArgumentException("Переход {$current->value} → {$target->value} запрещён");
        }

        $sources = [];
        foreach (self::transitions() as $from => $targets) {
            if (in_array($target, $targets, true)) {
                $sources[] = $from;
            }
        }

        $values = [
            'status' => $target->value,
            'updated_at' => now(),
        ];
        if ($target === OrderStatus::PaymentPending) {
            $rawTtl = config('orders.payment_ttl_minutes', 30);
            $ttlMinutes = is_int($rawTtl) ? $rawTtl : 30;
            $values['payment_expires_at'] = now()->addMinutes($ttlMinutes);
        }

        $updated = static::query()
            ->whereKey($this->id)
            ->whereIn('status', $sources)
            ->update($values);

        $this->refresh();

        if ($updated === 0 && $this->status !== $target) {
            throw new InvalidArgumentException("Переход {$current->value} → {$target->value} не выполнен");
        }
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasOne<Payment, $this> */
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
        $amount = Money::number($this->total_amount);

        return [
            'email' => $this->email,
            'user_id' => $this->user_id,
            'order_id' => $this->id,
            'store_id' => $this->store_id,
            'location_id' => $this->location_id,
            'total_amount' => $amount,
            'data' => array_merge([
                'order_id' => $this->id,
                'total_amount' => $amount,
                'currency' => $this->currency,
            ], $data),
        ];
    }
}
