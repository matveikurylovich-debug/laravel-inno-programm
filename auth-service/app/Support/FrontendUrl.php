<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

final class FrontendUrl
{
    public static function baseFor(Request $request): string
    {
        $origin = self::normalize($request->headers->get('Origin'));
        if ($origin !== null && self::isAllowed($request, $origin)) {
            return $origin;
        }

        $configured = self::configured();
        if ($configured !== '') {
            return $configured;
        }

        return self::normalize($request->getSchemeAndHttpHost()) ?? 'http://localhost';
    }

    public static function isAllowed(Request $request, string $origin): bool
    {
        $normalized = self::normalize($origin);
        if ($normalized === null) {
            return false;
        }

        foreach (self::allowed($request) as $expected) {
            if (hash_equals($expected, $normalized)) {
                return true;
            }
        }

        $originHost = parse_url($normalized, PHP_URL_HOST);
        $requestHost = $request->getHost();

        return is_string($originHost)
            && $originHost !== ''
            && strcasecmp($originHost, $requestHost) === 0;
    }

    /**
     * @return list<string>
     */
    private static function allowed(Request $request): array
    {
        $values = [
            self::configured(),
            self::normalize($request->getSchemeAndHttpHost()) ?? '',
        ];
        $unique = [];
        foreach ($values as $value) {
            if ($value !== '' && ! in_array($value, $unique, true)) {
                $unique[] = $value;
            }
        }

        return $unique;
    }

    private static function configured(): string
    {
        return rtrim((string) config('app.frontend_url', config('app.url')), '/');
    }

    private static function normalize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = rtrim(trim($value), '/');
        if (preg_match('#^https?://[^/\s]+$#', $trimmed) !== 1) {
            return null;
        }

        return $trimmed;
    }
}
