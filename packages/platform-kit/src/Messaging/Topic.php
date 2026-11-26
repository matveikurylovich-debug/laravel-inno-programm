<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Messaging;

enum Topic: string
{
    case AuthEvents = 'auth.events';
    case AuthNotifications = 'auth.notifications';
    case CatalogEvents = 'catalog.events';
    case InventoryEvents = 'inventory.events';
    case OrderEvents = 'order.events';
    case StockCommands = 'stock.commands';
    case AnalyticsEvents = 'analytics.events';

    public function dlqFor(string $consumerService): string
    {
        return "{$this->value}.dlq.{$consumerService}";
    }
}
