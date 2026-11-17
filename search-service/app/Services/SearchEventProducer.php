<?php

namespace App\Services;

use App\Jobs\PublishSearchEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SearchEventProducer
{
    public function performed(string $query, array $filters, int $resultsCount, int $tookMs): void
    {
        $payload = ['event_id' => (string) Str::uuid(), 'event' => 'search.performed', 'query' => $query,
            'filters' => $filters, 'results_count' => $resultsCount, 'took_ms' => $tookMs,
            'occurred_at' => now()->toIso8601String()];
        foreach (['kafka', 'rabbitmq'] as $destination) {
            try {
                PublishSearchEvent::dispatch($payload, $destination)->onConnection('redis')->onQueue('search-events');
            } catch (Throwable $exception) {
                Log::warning('Search telemetry could not be queued', ['event_id' => $payload['event_id'], 'destination' => $destination]);
            }
        }
    }
}
