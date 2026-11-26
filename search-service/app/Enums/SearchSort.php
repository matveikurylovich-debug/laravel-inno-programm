<?php

declare(strict_types=1);

namespace App\Enums;

enum SearchSort: string
{
    case Relevance = 'relevance';
    case PriceAsc = 'price_asc';
    case PriceDesc = 'price_desc';
}
