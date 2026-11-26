<?php

declare(strict_types=1);

namespace App\Messaging\Consumers;

use App\Enums\OrderNotificationEvent;

class OrderEventsConsumer extends AbstractNotificationConsumer
{
    protected function shouldProcess(string $event): bool
    {
        return OrderNotificationEvent::tryFrom($event) !== null;
    }
}
