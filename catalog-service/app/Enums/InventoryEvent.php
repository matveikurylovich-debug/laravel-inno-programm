<?php

declare(strict_types=1);

namespace App\Enums;

enum InventoryEvent: string
{
    case Reserved = 'stock.reserved';
    case ReservationFailed = 'stock.reservation_failed';
    case Committed = 'stock.committed';
    case CommitFailed = 'stock.commit_failed';
    case Changed = 'stock.changed';
}
