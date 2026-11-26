<?php

declare(strict_types=1);

namespace App\Support;

final class JwtRedisKey
{
    public const string REFRESH = 'jwt:refresh:';

    public const string GRACE = 'jwt:refresh:grace:';

    public const string USER = 'jwt:user:';

    public const string BLACKLIST = 'jwt:blacklist:jti:';

    public static function refresh(int $userId, string $jti): string
    {
        return self::REFRESH.$userId.':'.$jti;
    }

    public static function grace(int $userId, string $jti): string
    {
        return self::GRACE.$userId.':'.$jti;
    }

    public static function userRefreshSet(int $userId): string
    {
        return self::USER.$userId.':refresh';
    }

    public static function notBefore(int $userId): string
    {
        return self::USER.$userId.':nbf';
    }

    public static function blacklist(string $jti): string
    {
        return self::BLACKLIST.$jti;
    }
}
