<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\NonRetryableException;
use Ecommerce\PlatformKit\Messaging\EventEnvelope;

final class EventPayload
{
    /**
     * Нормализует конверт события и вложенный блок data в единый словарь атрибутов.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public static function attributes(array $body): array
    {
        $flattened = EventEnvelope::flatten($body);
        $nested = $flattened['data'] ?? null;

        if (is_array($nested)) {
            /** @var array<string, mixed> $merged */
            $merged = array_merge($flattened, $nested);

            return $merged;
        }

        return $flattened;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function requireEmail(array $attributes): string
    {
        $email = $attributes['email'] ?? null;

        if (! is_string($email) || trim($email) === '' || filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false) {
            throw new NonRetryableException('Поле email получателя отсутствует или некорректно.');
        }

        return trim($email);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function optionalUserId(array $attributes): int|string|null
    {
        if (! array_key_exists('user_id', $attributes) || $attributes['user_id'] === null) {
            return null;
        }

        $userId = $attributes['user_id'];

        if (is_int($userId)) {
            return $userId;
        }

        if (is_string($userId) && trim($userId) !== '') {
            return trim($userId);
        }

        throw new NonRetryableException('Поле user_id имеет недопустимый тип.');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function optionalEventId(array $attributes): ?string
    {
        if (! array_key_exists('event_id', $attributes) || $attributes['event_id'] === null) {
            return null;
        }

        $eventId = $attributes['event_id'];

        if (! is_string($eventId)) {
            throw new NonRetryableException('Поле event_id должно быть строкой.');
        }

        $trimmed = trim($eventId);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function requireOrderId(array $attributes): int|string
    {
        $orderId = $attributes['order_id'] ?? null;

        if (is_int($orderId) && $orderId > 0) {
            return $orderId;
        }

        if (is_string($orderId) && trim($orderId) !== '') {
            return trim($orderId);
        }

        throw new NonRetryableException('Поле order_id обязательно и должно быть непустым идентификатором.');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function requireAmount(array $attributes): float
    {
        $raw = $attributes['total_amount'] ?? $attributes['amount'] ?? null;

        return self::parseAmount($raw, 'Сумма заказа (total_amount/amount) обязательна и должна быть неотрицательным числом.');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function optionalAmount(array $attributes, string $key = 'total_amount', ?string $fallbackKey = 'amount'): ?float
    {
        $raw = $attributes[$key] ?? ($fallbackKey !== null ? ($attributes[$fallbackKey] ?? null) : null);

        if ($raw === null) {
            return null;
        }

        return self::parseAmount($raw, "Поле [{$key}] должно быть неотрицательным числом.");
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function requireCurrency(array $attributes): string
    {
        $currency = $attributes['currency'] ?? null;

        return self::parseCurrency($currency);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function optionalCurrency(array $attributes): ?string
    {
        if (! array_key_exists('currency', $attributes) || $attributes['currency'] === null) {
            return null;
        }

        return self::parseCurrency($attributes['currency']);
    }

    private static function parseAmount(mixed $raw, string $errorMessage): float
    {
        if (is_int($raw) || is_float($raw)) {
            $value = (float) $raw;
        } elseif (is_string($raw) && is_numeric(trim($raw))) {
            $value = (float) trim($raw);
        } else {
            throw new NonRetryableException($errorMessage);
        }

        if (! is_finite($value) || $value < 0.0) {
            throw new NonRetryableException($errorMessage);
        }

        return $value;
    }

    private static function parseCurrency(mixed $currency): string
    {
        if (! is_string($currency)) {
            throw new NonRetryableException('Поле currency должно быть трёхбуквенным ISO-кодом валюты.');
        }

        $normalized = strtoupper(trim($currency));

        if (preg_match('/^[A-Z]{3}$/', $normalized) !== 1) {
            throw new NonRetryableException('Поле currency должно быть трёхбуквенным ISO-кодом валюты.');
        }

        return $normalized;
    }
}
