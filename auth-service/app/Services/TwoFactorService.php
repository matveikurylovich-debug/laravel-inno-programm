<?php

namespace App\Services;

use App\Events\TwoFactorCodeRequested;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class TwoFactorService
{
    public const TTL_MINUTES = 5;

    public function challenge(User $user): int
    {
        $code = (string) random_int(100000, 999999);

        Cache::put($this->cacheKey($user->id), $code, now()->addMinutes(self::TTL_MINUTES));

        event(new TwoFactorCodeRequested($user, $code, self::TTL_MINUTES));

        return self::TTL_MINUTES;
    }

    public function verify(int $userId, string $code): User
    {
        $cachedCode = Cache::get($this->cacheKey($userId));

        if (! $cachedCode || $cachedCode !== $code) {
            throw ValidationException::withMessages([
                'code' => 'Неверный или просроченный код безопасности.',
            ]);
        }

        Cache::forget($this->cacheKey($userId));

        return User::findOrFail($userId);
    }

    public function cacheKey(int $userId): string
    {
        return "2fa_code_{$userId}";
    }
}
