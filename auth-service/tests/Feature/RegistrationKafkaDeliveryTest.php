<?php

declare(strict_types=1);

use App\Jobs\PublishUserRegisteredToKafka;
use App\Messaging\Messages\UserRegisteredMessage;
use App\Messaging\Producers\AuthEventsProducer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Junges\Kafka\Facades\Kafka;

it('publishes registration with an event id', function (): void {
    $delivery = new UserRegisteredMessage((string) Str::uuid(), 7, 'a@example.com', 'Ann', CarbonImmutable::parse('2026-11-25T12:00:00Z'));

    new PublishUserRegisteredToKafka($delivery)->handle(resolve(AuthEventsProducer::class));

    Kafka::assertPublishedOn('auth.events', null, function ($message) use ($delivery): bool {
        $body = $message->getBody();

        return is_array($body) && ($body['event_id'] ?? null) === $delivery->eventId;
    });
});
