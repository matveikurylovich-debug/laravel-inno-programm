<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\AuthNotificationEvent;
use App\Exceptions\NonRetryableException;
use App\Support\EventPayload;

final readonly class AuthNotificationData
{
    public function __construct(
        public AuthNotificationEvent $event,
        public string $email,
        public int|string|null $userId = null,
        public ?string $eventId = null,
        public ?string $name = null,
        public ?string $verificationUrl = null,
        public ?string $resetUrl = null,
        public ?int $expiresIn = null,
        public ?string $code = null,
        public ?int $ttlMinutes = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload, ?string $fallbackEvent = null): self
    {
        $attributes = EventPayload::attributes($payload);
        $rawEvent = $attributes['event'] ?? $fallbackEvent;

        if (! is_string($rawEvent) || trim($rawEvent) === '') {
            throw new NonRetryableException('Тип auth-события отсутствует или некорректен.');
        }

        $event = AuthNotificationEvent::tryFrom(trim($rawEvent));
        if ($event === null) {
            throw new NonRetryableException("Неподдерживаемое auth-событие [{$rawEvent}].");
        }

        $email = EventPayload::requireEmail($attributes);
        $userId = EventPayload::optionalUserId($attributes);
        $eventId = EventPayload::optionalEventId($attributes);

        return match ($event) {
            AuthNotificationEvent::UserRegistered => new self(
                event: $event,
                email: $email,
                userId: $userId,
                eventId: $eventId,
                name: self::requireNonEmptyString($attributes, 'name'),
                verificationUrl: self::optionalUrl($attributes, 'verification_url'),
            ),
            AuthNotificationEvent::PasswordReset => new self(
                event: $event,
                email: $email,
                userId: $userId,
                eventId: $eventId,
                resetUrl: self::requireUrl($attributes, 'reset_url'),
                expiresIn: self::requirePositiveInt($attributes, 'expires_in'),
            ),
            AuthNotificationEvent::TwoFactorCode => new self(
                event: $event,
                email: $email,
                userId: $userId,
                eventId: $eventId,
                code: self::requireNonEmptyString($attributes, 'code'),
                ttlMinutes: self::requirePositiveInt($attributes, 'ttl_minutes'),
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function requireNonEmptyString(array $attributes, string $field): string
    {
        $value = $attributes[$field] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new NonRetryableException("Поле [{$field}] обязательно и должно быть непустой строкой.");
        }

        return trim($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function requireUrl(array $attributes, string $field): string
    {
        $url = self::requireNonEmptyString($attributes, $field);

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new NonRetryableException("Поле [{$field}] должно быть корректным URL.");
        }

        return $url;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function optionalUrl(array $attributes, string $field): ?string
    {
        if (! array_key_exists($field, $attributes) || $attributes[$field] === null) {
            return null;
        }

        return self::requireUrl($attributes, $field);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function requirePositiveInt(array $attributes, string $field): int
    {
        $value = $attributes[$field] ?? null;

        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit(trim($value)) && (int) trim($value) > 0) {
            return (int) trim($value);
        }

        throw new NonRetryableException("Поле [{$field}] обязательно и должно быть положительным целым числом.");
    }
}
