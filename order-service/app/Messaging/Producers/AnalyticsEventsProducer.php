<?php

declare(strict_types=1);

namespace App\Messaging\Producers;

use Ecommerce\PlatformKit\Analytics\AnalyticsPublisher;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnalyticsEventsProducer
{
    public function __construct(private readonly AnalyticsPublisher $publisher) {}

    /**
     * @param  array<string, mixed>  $body
     */
    public function publish(string $event, array $body, bool $strict = false): void
    {
        try {
            $this->publisher->publish($event, $body, $strict);
        } catch (Throwable $exception) {
            if ($strict) {
                throw $exception;
            }

            Log::warning('Не удалось опубликовать событие аналитики', [
                'event' => $event,
                'exception' => $exception,
            ]);
        }
    }
}
