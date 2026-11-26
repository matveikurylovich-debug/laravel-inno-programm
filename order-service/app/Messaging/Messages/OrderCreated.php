<?php

declare(strict_types=1);

namespace App\Messaging\Messages;

use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Money;

final class OrderCreated
{
    /**
     * @return array<string, mixed>
     */
    public static function toPayload(Order $order): array
    {
        $order->loadMissing('items');

        return array_merge($order->notificationEnvelope(), [
            'items' => $order->items->map(static fn (OrderItem $item): array => [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'price' => Money::number($item->unit_price),
            ])->all(),
        ]);
    }
}
