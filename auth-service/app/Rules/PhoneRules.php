<?php

declare(strict_types=1);

namespace App\Rules;

final class PhoneRules
{
    public const string PATTERN = '/^\+[1-9]\d{7,14}$/';

    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        return ['required', 'string', 'max:20', 'regex:'.self::PATTERN];
    }

    public static function toE164(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (! preg_match('/^\+?[\d\s\-\(\)]+$/', $trimmed)) {
            return $trimmed;
        }

        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';
        if ($digits === '') {
            return '';
        }

        if (! str_starts_with($trimmed, '+') && str_starts_with($digits, '80') && strlen($digits) >= 11) {
            $digits = '375'.substr($digits, 2);
        }

        return '+'.$digits;
    }
}
