<?php

declare(strict_types=1);

namespace App\Messaging\Producers;

use App\Jobs\PublishSearchEvent;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

class SearchEventsProducer
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
        private readonly LoggerInterface $logger = new NullLogger,
        #[Config('services.search_events.connection', 'redis')]
        private readonly string $connection = 'redis',
        #[Config('services.search_events.queue', 'search-events')]
        private readonly string $queue = 'search-events',
    ) {}

    /**
     * @param  array<string, int|float|bool>  $filters
     */
    public function performed(string $query, array $filters, int $resultsCount, int $tookMs): void
    {
        $payload = [
            'event_id' => (string) Str::uuid(),
            'event' => 'search.performed',
            'query' => $query,
            'filters' => $filters,
            'results_count' => $resultsCount,
            'took_ms' => $tookMs,
            'occurred_at' => CarbonImmutable::now()->toIso8601String(),
        ];

        try {
            $job = new PublishSearchEvent($payload)
                ->onConnection($this->connection)
                ->onQueue($this->queue);

            $this->dispatcher->dispatch($job);
        } catch (Throwable) {
            $this->logger->warning('Search telemetry could not be queued', ['event_id' => $payload['event_id']]);
        }
    }
}
