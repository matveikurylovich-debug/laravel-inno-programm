<?php

namespace App\Listeners;

use App\Events\PasswordResetLinkRequested;
use App\Services\Kafka\AuthNotificationProducer;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendKafkaPasswordResetNotification
{
    public function __construct(
        protected AuthNotificationProducer $producer
    ) {}

    public function handle(PasswordResetLinkRequested $event): void
    {
        $user = $event->user;

        $base = $event->frontendBase !== ''
            ? rtrim($event->frontendBase, '/')
            : rtrim((string) config('app.frontend_url'), '/');
        $resetUrl = $base.'/reset-password/'.$event->token.'?email='.urlencode($user->getEmailForPasswordReset());

        try {
            $this->producer->sendPasswordReset(
                userId: $user->id,
                email: $user->email,
                name: $user->name,
                resetUrl: $resetUrl,
                expiresIn: (int) config('auth.passwords.users.expire'),
            );
        } catch (Throwable $e) {
            Log::error('Ошибка публикации события сброса пароля в Kafka: '.$e->getMessage());
        }
    }
}
