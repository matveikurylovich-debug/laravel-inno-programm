<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Http;

final class ProblemDetails
{
    /**
     * @param  array<string, mixed>  $errors
     * @return array{type: string, title: string, status: int, code: string, detail: string, message: string, errors?: array<string, mixed>}
     */
    public static function make(int $status, string $detail, array $errors = [], string $type = 'about:blank', ?string $code = null): array
    {
        $payload = [
            'type' => $type,
            'title' => self::title($status),
            'status' => $status,
            'code' => $code ?? self::defaultCode($status, $type),
            'detail' => $detail,
            'message' => $detail,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return $payload;
    }

    public static function defaultCode(int $status, string $type = 'about:blank'): string
    {
        if ($type === 'urn:problem:validation') {
            return 'validation_failed';
        }

        return match ($status) {
            400 => 'bad_request',
            401 => 'unauthenticated',
            403 => 'forbidden',
            404 => 'not_found',
            409 => 'conflict',
            422 => 'unprocessable_entity',
            429 => 'rate_limited',
            503 => 'service_unavailable',
            default => 'internal_error',
        };
    }

    public static function detail(int $status, string $message): string
    {
        $message = trim($message);
        if ($message === '' || self::leaks($message) || self::isFrameworkPlaceholder($message)) {
            return self::fallback($status);
        }

        return self::friendly($message) ?? $message;
    }

    public static function title(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            503 => 'Service Unavailable',
            default => 'Error',
        };
    }

    public static function fallback(int $status): string
    {
        return match ($status) {
            400 => 'Некорректный запрос.',
            401 => 'Требуется авторизация.',
            403 => 'Недостаточно прав.',
            404 => 'Ресурс не найден.',
            409 => 'Конфликт состояния.',
            422 => 'Данные не прошли проверку.',
            429 => 'Слишком много запросов.',
            503 => 'Сервис временно недоступен.',
            default => 'Внутренняя ошибка сервера.',
        };
    }

    public static function isFrameworkPlaceholder(string $message): bool
    {
        return preg_match('/^The route .+ could not be found\.$/', $message) === 1
            || in_array($message, ['Not Found', 'Unauthorized', 'Forbidden', 'HTTP 404 Not Found'], true);
    }

    public static function leaks(string $message): bool
    {
        return preg_match('/No query results for model|SQLSTATE|Stack trace|vendor[\\\\\\/]/i', $message) === 1
            || str_contains($message, 'App\\')
            || str_contains($message, 'Models\\');
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    public static function firstError(array $errors): ?string
    {
        foreach ($errors as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }

            if (! is_array($value)) {
                continue;
            }

            foreach ($value as $item) {
                if (is_string($item) && $item !== '') {
                    return $item;
                }
            }
        }

        return null;
    }

    private static function friendly(string $message): ?string
    {
        return match ($message) {
            'Unauthenticated.' => 'Требуется авторизация.',
            'Full authentication is required to access this resource.' => 'Требуется авторизация.',
            'This action is unauthorized.' => 'Недостаточно прав.',
            'The given data was invalid.' => null,
            'Server Error' => 'Внутренняя ошибка сервера.',
            default => null,
        };
    }
}
