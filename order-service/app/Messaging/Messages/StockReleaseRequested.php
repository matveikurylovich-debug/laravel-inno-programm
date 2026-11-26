<?php

declare(strict_types=1);

namespace App\Messaging\Messages;

use App\Models\Order;

final class StockReleaseRequested
{
    /**
     * @return array{order_id: string, store_id: int}
     */
    public static function for(Order $order): array
    {
        return [
            'order_id' => $order->id,
            'store_id' => (int) $order->store_id,
        ];
    }
}
