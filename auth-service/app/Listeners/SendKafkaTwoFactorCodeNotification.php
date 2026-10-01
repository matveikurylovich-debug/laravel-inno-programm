<?php

namespace App\Listeners;

use App\Events\TwoFactorCodeRequested;
use App\Services\Kafka\AuthNotificationProducer;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendKafkaTwoFactorCodeNotification
{
    public function __construct(
        private readonly AuthNotificationProducer $producer
    ) {}

    public function handle(TwoFactorCodeRequested $event): void
    {
        try {
            $this->producer->sendTwoFactorCode(
                userId: $event->user->id,
                email: $event->user->email,
                code: $event->code,
                ttlMinutes: $event->ttlMinutes
            );
        } catch (Throwable $e) {
            Log::error('Ошибка публикации кода 2FA в Kafka: '.$e->getMessage());
        }
    }
}
