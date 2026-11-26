<?php

declare(strict_types=1);

namespace App\Messaging\Producers;

use App\Enums\AuthEvent;
use App\Support\TwoFactorCacheKey;
use Ecommerce\PlatformKit\Messaging\EventEnvelope;
use Illuminate\Support\Str;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\Message;

class AuthEventsProducer
{
    public const QUEUE = 'auth-events';

    /**
     * @param  array<string, mixed>  $body
     */
    private function publish(AuthEvent $event, array $body): void
    {
        $eventId = isset($body['event_id']) && is_string($body['event_id']) ? $body['event_id'] : (string) Str::uuid();
        $body['event_id'] = $eventId;
        $body['event'] = $event->value;
        $envelope = EventEnvelope::wrap($event->value, 'auth-service', $body, $eventId);

        Kafka::publish()
            ->onTopic((string) config('kafka.topics.auth_events'))
            ->withMessage(new Message(
                headers: $envelope->headers(),
                body: $envelope->toArray(),
            ))
            ->send();
    }

    public function sendUserRegistered(int|string $userId, string $email, string $name, ?string $eventId = null): void
    {
        $this->publish(AuthEvent::UserRegistered, [
            'event_id' => $eventId ?? (string) Str::uuid(),
            'user_id' => $userId,
            'email' => $email,
            'data' => ['name' => $name],
        ]);
    }

    public function sendPasswordReset(
        int|string $userId,
        string $email,
        string $name,
        string $resetUrl,
        int $expiresIn,
    ): void {
        $this->publish(AuthEvent::PasswordReset, [
            'user_id' => $userId,
            'email' => $email,
            'data' => [
                'name' => $name,
                'reset_url' => $resetUrl,
                'expires_in' => $expiresIn,
            ],
        ]);
    }

    public function sendTwoFactorCode(int|string $userId, string $email, string $code, int $ttlMinutes = TwoFactorCacheKey::TTL_MINUTES): void
    {
        $this->publish(AuthEvent::TwoFactorCode, [
            'user_id' => $userId,
            'email' => $email,
            'data' => [
                'code' => $code,
                'ttl_minutes' => $ttlMinutes,
            ],
        ]);
    }
}
