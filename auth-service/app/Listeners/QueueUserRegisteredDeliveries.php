<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Jobs\PublishUserRegisteredToAnalytics;
use App\Jobs\PublishUserRegisteredToKafka;
use App\Messaging\Messages\UserRegisteredMessage as RegisteredDelivery;
use App\Messaging\Producers\AuthEventsProducer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Str;

class QueueUserRegisteredDeliveries
{
    public function handle(Registered $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $user = $event->user;
        $delivery = new RegisteredDelivery(
            (string) Str::uuid(),
            (int) $user->id,
            (string) $user->email,
            (string) $user->name,
            CarbonImmutable::now(),
        );

        dispatch(new PublishUserRegisteredToKafka($delivery))->onConnection('database')->onQueue(AuthEventsProducer::QUEUE);
        dispatch(new PublishUserRegisteredToAnalytics($delivery))->onConnection('database')->onQueue(AuthEventsProducer::QUEUE);
    }
}
