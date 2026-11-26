<?php

declare(strict_types=1);

namespace App\Messaging\Messages;

use App\Models\Order;
use App\Support\Money;

final class OrderPaymentFailed
{
    /**
     * @return array{order_id: string, store_id: int, user_id: int, total_amount: int|float}
     */
    public static function for(Order $order): array
    {
        return [
            'order_id' => $order->id,
            'store_id' => (int) $order->store_id,
            'user_id' => (int) $order->user_id,
            'total_amount' => Money::number($order->total_amount),
        ];
    }
}
