<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TwoFactorCodeRequested;
use App\Messaging\Producers\AuthEventsProducer;
use App\Services\Auth\TwoFactorService;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;

class PublishTwoFactorCodeRequested implements ShouldBeEncrypted, ShouldQueue
{
    public string $queue = AuthEventsProducer::QUEUE;

    public int $tries = 10;

    public function __construct(
        private readonly AuthEventsProducer $producer
    ) {}

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(TwoFactorService::TTL_MINUTES);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [1, 3, 10, 30];
    }

    public function handle(TwoFactorCodeRequested $event): void
    {
        $this->producer->sendTwoFactorCode(
            userId: $event->user->id,
            email: $event->user->email,
            code: $event->code,
            ttlMinutes: $event->ttlMinutes,
        );
    }
}
