<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case Created = 'created';
    case StockReserved = 'stock_reserved';
    case PaymentPending = 'payment_pending';
    case Paid = 'paid';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
}
