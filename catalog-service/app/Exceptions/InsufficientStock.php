<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class InsufficientStock extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Insufficient stock');
    }
}
