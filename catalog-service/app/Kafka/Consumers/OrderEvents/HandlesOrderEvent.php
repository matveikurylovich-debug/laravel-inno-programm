<?php

namespace App\Kafka\Consumers\OrderEvents;

interface HandlesOrderEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void;
}
