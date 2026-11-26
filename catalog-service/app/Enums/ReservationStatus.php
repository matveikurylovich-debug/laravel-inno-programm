<?php

declare(strict_types=1);

namespace App\Enums;

enum ReservationStatus: string
{
    case Reserved = 'reserved';
    case Confirmed = 'confirmed';
    case Released = 'released';
}
