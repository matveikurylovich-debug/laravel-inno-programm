<?php

declare(strict_types=1);

namespace App\Messaging\Handlers\OrderEvents;

interface OrderEventHandler
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void;
}
