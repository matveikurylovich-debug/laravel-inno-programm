<?php

namespace App\Listeners;

use App\Jobs\PublishRegistrationEvent;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Str;

class SendKafkaRegistrationNotification
{
    public function handle(Registered $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $user = $event->user;
        $payload = ['event_id' => (string) Str::uuid(), 'user_id' => $user->id, 'email' => $user->email,
            'name' => $user->name, 'occurred_at' => now()->toIso8601String()];
        foreach (['kafka', 'rabbitmq'] as $destination) {
            // The database queue uses the registration transaction: this is the durable outbox.
            PublishRegistrationEvent::dispatch($payload, $destination)->onConnection('database')->onQueue('auth-events');
        }
    }
}
