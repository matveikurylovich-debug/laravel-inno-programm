<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderNotificationEvent: string
{
    case Paid = 'order.paid';
    case Confirmed = 'order.confirmed';
    case Cancelled = 'order.cancelled';
}
