<?php

declare(strict_types=1);

namespace App\Enums;

enum CatalogEventType: string
{
    case ProductCreated = 'product.created';
    case ProductUpdated = 'product.updated';
    case ProductDeleted = 'product.deleted';
    case CategoryCreated = 'category.created';
    case CategoryUpdated = 'category.updated';
    case CategoryDeleted = 'category.deleted';
    case StockChanged = 'stock.changed';
}
