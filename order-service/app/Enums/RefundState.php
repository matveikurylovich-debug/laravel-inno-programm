<?php

declare(strict_types=1);

namespace App\Enums;

enum RefundState: string
{
    case Succeeded = 'succeeded';
    case Pending = 'pending';
}
