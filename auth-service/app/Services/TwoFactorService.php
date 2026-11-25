<?php

namespace App\Services;

use App\Events\TwoFactorCodeRequested;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TwoFactorService
{
    public const TTL_MINUTES = 5;

    public const MAX_ATTEMPTS = 5;

    public const MAX_RESENDS = 3;

    /**
     * @return array{challenge_id: string, expires_in: int}
     */
    public function challenge(User $user): array
    {
        $previous = Cache::get($this->userIndexKey($user->id));
        if (is_string($previous) && $previous !== '') {
            $this->forget($previous, $user->id);
        }

        $challengeId = Str::random(40);
        $code = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes(self::TTL_MINUTES)->getTimestamp();
        $ttl = $expiresAt - time();

        Cache::put($this->challengeKey($challengeId), [
            'user_id' => $user->id,
            'code_hash' => hash('sha256', $code),
            'expires_at' => $expiresAt,
        ], $ttl);
        Cache::put($this->attemptsKey($challengeId), 0, $ttl);
        Cache::put($this->resendKey($challengeId), 0, $ttl);
        Cache::put($this->userIndexKey($user->id), $challengeId, $ttl);

        event(new TwoFactorCodeRequested($user, $code, self::TTL_MINUTES));

        return [
            'challenge_id' => $challengeId,
            'expires_in' => self::TTL_MINUTES * 60,
        ];
    }

    public function verify(string $challengeId, string $code): User
    {
        if (Cache::get($this->lockedKey($challengeId))) {
            throw new HttpException(429, 'Превышено число попыток. Войдите заново.');
        }

        $payload = $this->payload($challengeId);
        if ($payload === null) {
            throw ValidationException::withMessages([
                'code' => 'Неверный или просроченный код безопасности.',
            ]);
        }

        $attempts = (int) Cache::increment($this->attemptsKey($challengeId));
        if ($attempts > self::MAX_ATTEMPTS) {
            $this->lock($challengeId, $payload['user_id']);

            throw new HttpException(429, 'Превышено число попыток. Войдите заново.');
        }

        if (! hash_equals($payload['code_hash'], hash('sha256', $code))) {
            throw ValidationException::withMessages([
                'code' => 'Неверный или просроченный код безопасности.',
            ]);
        }

        $this->forget($challengeId, $payload['user_id']);

        return User::query()->findOrFail($payload['user_id']);
    }

    /**
     * @return array{challenge_id: string, expires_in: int}
     */
    public function resend(string $challengeId): array
    {
        if (Cache::get($this->lockedKey($challengeId))) {
            throw new HttpException(429, 'Превышено число попыток. Войдите заново.');
        }

        $payload = $this->payload($challengeId);
        if ($payload === null) {
            throw ValidationException::withMessages([
                'challenge_id' => 'Сначала войдите по паролю.',
            ]);
        }

        $resent = (int) Cache::increment($this->resendKey($challengeId));
        if ($resent > self::MAX_RESENDS) {
            throw new HttpException(429, 'Слишком много повторных отправок. Войдите заново.');
        }

        $code = (string) random_int(100000, 999999);
        $payload['code_hash'] = hash('sha256', $code);
        $this->store($challengeId, $payload);

        $user = User::query()->findOrFail($payload['user_id']);
        event(new TwoFactorCodeRequested($user, $code, self::TTL_MINUTES));

        return [
            'challenge_id' => $challengeId,
            'expires_in' => max(1, $payload['expires_at'] - time()),
        ];
    }

    /**
     * @return array{user_id: int, code_hash: string, expires_at: int}|null
     */
    private function payload(string $challengeId): ?array
    {
        $payload = Cache::get($this->challengeKey($challengeId));
        if (! is_array($payload) || ! isset($payload['user_id'], $payload['code_hash'], $payload['expires_at'])) {
            return null;
        }

        if ((int) $payload['expires_at'] <= time()) {
            $this->forget($challengeId, (int) $payload['user_id']);

            return null;
        }

        return [
            'user_id' => (int) $payload['user_id'],
            'code_hash' => (string) $payload['code_hash'],
            'expires_at' => (int) $payload['expires_at'],
        ];
    }

    /**
     * @param  array{user_id: int, code_hash: string, expires_at: int}  $payload
     */
    private function store(string $challengeId, array $payload): void
    {
        $seconds = $payload['expires_at'] - time();
        if ($seconds <= 0) {
            return;
        }

        Cache::put($this->challengeKey($challengeId), $payload, $seconds);
    }

    private function lock(string $challengeId, int $userId): void
    {
        $this->forget($challengeId, $userId);
        Cache::put($this->lockedKey($challengeId), true, now()->addMinutes(self::TTL_MINUTES));
    }

    private function forget(string $challengeId, int $userId): void
    {
        Cache::forget($this->challengeKey($challengeId));
        Cache::forget($this->attemptsKey($challengeId));
        Cache::forget($this->resendKey($challengeId));
        if (Cache::get($this->userIndexKey($userId)) === $challengeId) {
            Cache::forget($this->userIndexKey($userId));
        }
    }

    private function challengeKey(string $challengeId): string
    {
        return '2fa_challenge_'.$challengeId;
    }

    private function attemptsKey(string $challengeId): string
    {
        return '2fa_attempts_'.$challengeId;
    }

    private function resendKey(string $challengeId): string
    {
        return '2fa_resend_'.$challengeId;
    }

    private function lockedKey(string $challengeId): string
    {
        return '2fa_locked_'.$challengeId;
    }

    private function userIndexKey(int $userId): string
    {
        return '2fa_user_'.$userId;
    }
}
