<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property string $order_id
 * @property string|null $stripe_payment_intent_id
 * @property string|null $stripe_refund_id
 * @property string|null $stripe_client_secret
 * @property string $amount
 * @property string $currency
 * @property PaymentStatus $status
 * @property array<string, mixed>|null $raw_response
 * @property-read string|null $publishable_key
 */
class Payment extends Model
{
    #[Override]
    protected $fillable = [
        'order_id',
        'stripe_payment_intent_id',
        'stripe_refund_id',
        'stripe_client_secret',
        'amount',
        'currency',
        'status',
        'raw_response',
    ];

    #[Override]
    protected $hidden = [
        'stripe_client_secret',
        'raw_response',
    ];

    #[Override]
    protected $appends = [
        'publishable_key',
    ];

    #[Override]
    protected $casts = [
        'amount' => 'decimal:2',
        'raw_response' => 'array',
        'status' => PaymentStatus::class,
    ];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return Attribute<non-empty-string|null, never>
     */
    protected function publishableKey(): Attribute
    {
        return Attribute::get(static function (): ?string {
            $key = config('services.stripe.publishable');

            return is_string($key) && $key !== '' ? $key : null;
        });
    }
}
