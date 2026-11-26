<?php

declare(strict_types=1);

namespace App\Messaging\Messages;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Support\Money;

final class OrderCancelled
{
    /**
     * @return array<string, mixed>
     */
    public static function toPayload(Order $order, string $previousStatus, string|int|float|null $refundedAmount = null): array
    {
        $refunded = Money::decimal(
            $refundedAmount ?? ($order->payment_status === PaymentStatus::Refunded ? $order->total_amount : '0.00')
        );

        return array_merge($order->notificationEnvelope([
            'reason' => $order->cancel_reason,
            'refunded_amount' => $refunded,
        ]), [
            'previous_status' => $previousStatus,
            'reason' => $order->cancel_reason,
            'refunded_amount' => $refunded,
            'currency' => strtoupper($order->currency !== '' ? $order->currency : 'USD'),
        ]);
    }
}
