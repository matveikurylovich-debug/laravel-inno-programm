<?php

namespace App\Listeners;

use App\Services\Analytics\AnalyticsEventPublisher;
use App\Services\Kafka\AuthNotificationProducer;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendKafkaRegistrationNotification
{
    public function __construct(
        protected AuthNotificationProducer $producer,
        protected AnalyticsEventPublisher $analytics,
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

        $this->analytics->publish('user.registered', [
            'user_id' => (int) $user->id,
            'occurred_at' => now()->toIso8601String(),
        ]);
    }
}