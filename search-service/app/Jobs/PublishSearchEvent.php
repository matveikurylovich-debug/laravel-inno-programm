<?php

declare(strict_types=1);

namespace App\Jobs;

use Ecommerce\PlatformKit\Analytics\AnalyticsPublisher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PublishSearchEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 8;

    public int $timeout = 30;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [2, 10, 30, 60, 300];
    }

    public function handle(AnalyticsPublisher $analytics): void
    {
        $analytics->publish('search.performed', $this->payload, true);
    }
}
