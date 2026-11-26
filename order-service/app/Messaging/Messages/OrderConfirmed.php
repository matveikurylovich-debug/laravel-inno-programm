<?php

declare(strict_types=1);

namespace App\Messaging\Messages;

use App\Models\Order;

final class OrderConfirmed
{
    /**
     * @return array<string, mixed>
     */
    public static function toPayload(Order $order): array
    {
        return $order->notificationEnvelope();
    }
}
