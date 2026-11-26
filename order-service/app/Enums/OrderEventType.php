<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderEventType: string
{
    case Created = 'order.created';
    case Paid = 'order.paid';
    case Confirmed = 'order.confirmed';
    case Cancelled = 'order.cancelled';
    case Refunded = 'order.refunded';
    case PaymentFailed = 'order.payment_failed';
    case StockReleaseRequested = 'stock.release_requested';
    case StockCommitRequested = 'stock.commit_requested';
}
