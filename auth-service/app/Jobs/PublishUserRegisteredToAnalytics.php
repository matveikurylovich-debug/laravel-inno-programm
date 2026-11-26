<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AuthEvent;
use App\Messaging\Messages\UserRegisteredMessage;
use App\Messaging\Producers\AnalyticsEventsProducer;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublishUserRegisteredToAnalytics implements ShouldQueue
{
    use Queueable;

    public int $tries = 10;

    public function __construct(public readonly UserRegisteredMessage $event) {}

    public function retryUntil(): DateTimeInterface
    {
        return now()->addDay();
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [2, 10, 30, 60, 300];
    }

    public function handle(AnalyticsEventsProducer $analytics): void
    {
        $analytics->publish(AuthEvent::UserRegistered->value, $this->event->toAnalytics(), true);
    }

    public function failed(Throwable $exception): void
    {
        Log::critical('Failed to deliver user.registered event to analytics', [
            'event_id' => $this->event->eventId,
            'exception' => $exception,
        ]);
    }
}
