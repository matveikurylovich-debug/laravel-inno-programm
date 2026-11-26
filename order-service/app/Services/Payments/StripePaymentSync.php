<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

final class StripePaymentSync
{
    /**
     * @param  array<string, mixed>  $paymentIntent
     */
    public static function orderId(array $paymentIntent): ?string
    {
        $metadata = $paymentIntent['metadata'] ?? null;
        $orderId = is_array($metadata) ? ($metadata['order_id'] ?? null) : null;
        $intentId = $paymentIntent['id'] ?? null;

        if (! is_string($orderId) || $orderId === '') {
            Log::warning('Stripe webhook: отсутствует metadata.order_id', [
                'payment_intent_id' => is_string($intentId) ? $intentId : null,
            ]);

            return null;
        }

        return $orderId;
    }

    /**
     * @param  array<string, mixed>  $paymentIntent
     */
    public static function apply(Order $order, array $paymentIntent, PaymentStatus $status): void
    {
        $intentId = $paymentIntent['id'] ?? null;
        $intentId = is_string($intentId) && $intentId !== '' ? $intentId : null;
        $payment = null;

        if ($intentId !== null) {
            $payment = Payment::query()
                ->where('stripe_payment_intent_id', $intentId)
                ->first();
        }

        $payment ??= $order->payment()->first();

        if ($payment === null) {
            Log::warning('Stripe webhook: платёж для заказа не найден', [
                'order_id' => $order->id,
                'payment_intent_id' => $intentId,
                'status' => $status->value,
            ]);

            return;
        }

        $payment->update([
            'status' => $status,
            'raw_response' => $paymentIntent,
        ]);
    }
}
