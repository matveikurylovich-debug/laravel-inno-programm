<?php

declare(strict_types=1);

namespace App\Messaging\Messages;

use App\Models\Order;
use App\Models\Payment;
use App\Support\Money;

final class OrderRefunded
{
    /**
     * @param  array<string, mixed>|null  $refund
     * @return array{order_id: string, user_id: int, store_id: int, refunded_amount: string, currency: string}
     */
    public static function toPayload(
        Order $order,
        ?Payment $payment = null,
        ?array $refund = null,
        ?string $refundedAmount = null,
        ?string $currency = null,
    ): array {
        $raw = $refund ?? [];

        $amount = $refundedAmount;
        if ($amount === null && isset($raw['amount']) && is_int($raw['amount']) && $raw['amount'] > 0) {
            $amount = Money::fromCents($raw['amount']);
        }
        $amount ??= Money::decimal($payment instanceof Payment ? $payment->amount : $order->total_amount);

        $resolvedCurrency = $currency;
        if (($resolvedCurrency === null || $resolvedCurrency === '') && isset($raw['currency']) && is_string($raw['currency']) && $raw['currency'] !== '') {
            $resolvedCurrency = $raw['currency'];
        }
        if (($resolvedCurrency === null || $resolvedCurrency === '') && $payment instanceof Payment && $payment->currency !== '') {
            $resolvedCurrency = $payment->currency;
        }
        if ($resolvedCurrency === null || $resolvedCurrency === '') {
            $resolvedCurrency = $order->currency;
        }

        return [
            'order_id' => (string) $order->id,
            'user_id' => (int) $order->user_id,
            'store_id' => (int) $order->store_id,
            'refunded_amount' => Money::decimal($amount),
            'currency' => strtoupper($resolvedCurrency !== '' ? $resolvedCurrency : 'USD'),
        ];
    }
}
