<?php

namespace App\Listeners;

use App\Services\Kafka\AuthNotificationProducer;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendKafkaRegistrationNotification
{
    public function __construct(
        protected AuthNotificationProducer $producer
    ) {}

    public function handle(Registered $event): void
    {
        $user = $event->user;

        try {
            $this->producer->sendUserRegistered(
                userId: $user->id,
                email: $user->email,
                name: $user->name
            );
        } catch (Throwable $e) {
            Log::error('Ошибка публикации события регистрации в Kafka: ' . $e->getMessage());
        }
    }
}