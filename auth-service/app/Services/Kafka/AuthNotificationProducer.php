<?php

namespace App\Services\Kafka;

use Junges\Kafka\Facades\Kafka;

class AuthNotificationProducer
{
    private const TOPIC = 'auth.notifications';

    /**
     * Уведомление о регистрации пользователя.
     */
    public function sendUserRegistered(int|string $userId, string $email, string $name): void
    {
        Kafka::publish()
            ->onTopic(self::TOPIC)
            ->withBodyKey('event', 'user.registered')
            ->withBodyKey('user_id', $userId)
            ->withBodyKey('email', $email)
            ->withBodyKey('data', [
                'name' => $name,
            ])
            ->send();
    }

    public function sendPasswordReset(
        int|string $userId,
        string $email,
        string $name,
        string $resetUrl,
        int $expiresIn,
    ): void {
        Kafka::publish()
            ->onTopic(self::TOPIC)
            ->withBodyKey('event', 'auth.password_reset')
            ->withBodyKey('user_id', $userId)
            ->withBodyKey('email', $email)
            ->withBodyKey('data', [
                'name' => $name,
                'reset_url' => $resetUrl,
                'expires_in' => $expiresIn,
            ])
            ->send();
    }

    public function sendTwoFactorCode(int|string $userId, string $email, string $code, int $ttlMinutes = 5): void
    {
        Kafka::publish()
            ->onTopic(self::TOPIC)
            ->withBodyKey('event', 'auth.two_factor_code')
            ->withBodyKey('user_id', $userId)
            ->withBodyKey('email', $email)
            ->withBodyKey('data', [
                'code' => $code,
                'ttl_minutes' => $ttlMinutes,
            ])
            ->send();
    }

    public function sendProfileUpdateConfirm(int|string $userId, string $email, string $name): void
    {
        Kafka::publish()
            ->onTopic(self::TOPIC)
            ->withBodyKey('event', 'auth.profile_update_confirm')
            ->withBodyKey('user_id', $userId)
            ->withBodyKey('email', $email)
            ->withBodyKey('data', [
                'name' => $name,
            ])
            ->send();
    }
}
