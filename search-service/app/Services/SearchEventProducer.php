<?php

namespace App\Services;

use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\Message;

class SearchEventProducer
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function performed(string $query, array $filters, int $resultsCount, int $tookMs): void
    {
        Kafka::publish()
            ->onTopic((string) config('kafka.topics.search_events', 'search.events'))
            ->withMessage(new Message(
                headers: ['event-type' => 'search.performed', 'source' => 'search-service'],
                body: [
                    'event' => 'search.performed',
                    'query' => $query,
                    'filters' => $filters,
                    'results_count' => $resultsCount,
                    'took_ms' => $tookMs,
                    'timestamp' => now()->toIso8601String(),
                ],
                partition: -1,
            ))
            ->send();
    }
}
