<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class StockNotFound extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('stock-not-found');
    }
}
