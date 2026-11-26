<?php

declare(strict_types=1);

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class Money
{
    public static function decimal(string|int|float $amount): string
    {
        return (string) BigDecimal::of(self::plain($amount))->toScale(2, RoundingMode::HalfUp);
    }

    public static function multiply(string|int|float $unitPrice, int $quantity): string
    {
        return (string) BigDecimal::of(self::decimal($unitPrice))
            ->multipliedBy($quantity)
            ->toScale(2, RoundingMode::HalfUp);
    }

    public static function add(string $left, string $right): string
    {
        return (string) BigDecimal::of($left)->plus($right)->toScale(2, RoundingMode::HalfUp);
    }

    /**
     * JSON number for Kafka and analytics. Go decodes total_amount as float64.
     */
    public static function number(string|int|float $amount): int|float
    {
        $decimal = self::decimal($amount);

        if (str_ends_with($decimal, '.00')) {
            return BigDecimal::of($decimal)->toScale(0, RoundingMode::Unnecessary)->toInt();
        }

        return (float) $decimal;
    }

    public static function cents(string|int|float $amount): int
    {
        return BigDecimal::of(self::decimal($amount))
            ->multipliedBy(100)
            ->toScale(0, RoundingMode::HalfUp)
            ->toInt();
    }

    public static function fromCents(int $cents): string
    {
        return (string) BigDecimal::of($cents)
            ->dividedBy(100, 2, RoundingMode::HalfUp);
    }

    private static function plain(string|int|float $amount): string
    {
        if (is_int($amount)) {
            return (string) $amount;
        }

        if (is_string($amount)) {
            return $amount;
        }

        return number_format($amount, 2, '.', '');
    }
}
