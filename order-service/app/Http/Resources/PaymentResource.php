<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Payment;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    private bool $exposeClientSecret = false;

    public function exposeClientSecret(bool $expose = true): self
    {
        $this->exposeClientSecret = $expose;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payment = $this->resource;

        if (! $payment instanceof Payment) {
            throw new LogicException('PaymentResource requires a Payment model.');
        }

        return [
            'id' => $payment->id,
            'status' => $payment->status->value,
            'amount' => Money::decimal($payment->amount),
            'currency' => $payment->currency,
            'publishable_key' => $payment->publishable_key,
            'stripe_client_secret' => $this->when(
                $this->exposeClientSecret,
                $payment->stripe_client_secret,
            ),
        ];
    }
}
