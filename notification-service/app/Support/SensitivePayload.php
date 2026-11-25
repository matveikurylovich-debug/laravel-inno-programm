<?php

namespace App\Support;

final class SensitivePayload
{
    /** @var list<string> */
    private const KEYS = ['code', 'reset_url'];

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function redact(array $payload): array
    {
        foreach (self::KEYS as $key) {
            unset($payload[$key]);
        }

        if (isset($payload['data']) && is_array($payload['data'])) {
            foreach (self::KEYS as $key) {
                unset($payload['data'][$key]);
            }
        }

        if (isset($payload['original_payload']) && is_array($payload['original_payload'])) {
            $payload['original_payload'] = self::redact($payload['original_payload']);
        }

        return $payload;
    }
}
