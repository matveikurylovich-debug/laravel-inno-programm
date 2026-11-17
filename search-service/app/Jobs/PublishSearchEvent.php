<?php

namespace App\Jobs;

use App\Services\Analytics\AnalyticsEventPublisher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\Message;

class PublishSearchEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public int $timeout = 30;

    public function __construct(public array $payload, public string $destination) {}

    public function backoff(): array
    {
        return [2, 10, 30, 60, 300];
    }

    public function handle(AnalyticsEventPublisher $analytics): void
    {
        if ($this->destination === 'rabbitmq') {
            $analytics->publish('search.performed', $this->payload, true);

            return;
        }
        Kafka::publish()->onTopic((string) config('kafka.topics.search_events', 'search.events'))
            ->withMessage(new Message(headers: ['event-type' => 'search.performed', 'source' => 'search-service'], body: $this->payload))
            ->send();
    }
}
