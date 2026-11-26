<?php

declare(strict_types=1);

namespace App\Messaging\Consumers;

class AuthEventsConsumer extends AbstractNotificationConsumer
{
    protected function shouldProcess(string $event): bool
    {
        return true;
    }
}
