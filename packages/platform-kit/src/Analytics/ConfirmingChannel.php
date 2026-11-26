<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Analytics;

interface ConfirmingChannel
{
    public function publish(string $body, string $exchange, string $routingKey): void;
}
