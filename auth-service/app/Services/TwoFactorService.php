<?php

namespace App\Services;

use App\Events\TwoFactorCodeRequested;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TwoFactorService
{
    public const TTL_MINUTES = 5;

    public const MAX_ATTEMPTS = 5;

    /**
     * @return array{challenge_id: string, expires_in: int}
     */
    public function challenge(User $user): array
    {
        $previous = Cache::get($this->userIndexKey($user->id));
        if (is_string($previous) && $previous !== '') {
            Cache::forget($this->challengeKey($previous));
        }

        $challengeId = (string) Str::uuid();
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(self::TTL_MINUTES)->getTimestamp();

        $this->store($challengeId, [
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => $expiresAt,
        ]);
        Cache::put($this->userIndexKey($user->id), $challengeId, $expiresAt - time());

        event(new TwoFactorCodeRequested($user, $code, self::TTL_MINUTES));

        return [
            'challenge_id' => $challengeId,
            'expires_in' => self::TTL_MINUTES * 60,
        ];
    }

    public function verify(string $challengeId, string $code): User
    {
        $payload = $this->payload($challengeId);
        if ($payload === null) {
            throw ValidationException::withMessages([
                'code' => 'Неверный или просроченный код безопасности.',
            ]);
        }

        if ($payload['attempts'] >= self::MAX_ATTEMPTS) {
            $this->forget($challengeId, $payload['user_id']);

            throw ValidationException::withMessages([
                'code' => 'Превышено число попыток. Войдите заново.',
            ]);
        }

        if (! Hash::check($code, $payload['code_hash'])) {
            $payload['attempts']++;
            if ($payload['attempts'] >= self::MAX_ATTEMPTS) {
                $this->forget($challengeId, $payload['user_id']);
            } else {
                $this->store($challengeId, $payload);
            }

            throw ValidationException::withMessages([
                'code' => 'Неверный или просроченный код безопасности.',
            ]);
        }

        $userId = $payload['user_id'];
        $this->forget($challengeId, $userId);

        return User::query()->findOrFail($userId);
    }

    /**
     * @return array{challenge_id: string, expires_in: int}
     */
    public function resend(string $challengeId): array
    {
        $payload = $this->payload($challengeId);
        if ($payload === null) {
            throw ValidationException::withMessages([
                'challenge_id' => 'Сначала войдите по паролю.',
            ]);
        }

        $user = User::query()->find($payload['user_id']);
        if (! $user instanceof User) {
            $this->forget($challengeId, $payload['user_id']);

            throw ValidationException::withMessages([
                'challenge_id' => 'Сначала войдите по паролю.',
            ]);
        }

        return $this->challenge($user);
    }

    /**
     * @return array{user_id: int, code_hash: string, attempts: int, expires_at: int}|null
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
            'attempts' => (int) ($payload['attempts'] ?? 0),
            'expires_at' => (int) $payload['expires_at'],
        ];
    }

    /**
     * @param  array{user_id: int, code_hash: string, attempts: int, expires_at: int}  $payload
     */
    private function store(string $challengeId, array $payload): void
    {
        $seconds = $payload['expires_at'] - time();
        if ($seconds <= 0) {
            return;
        }

        Cache::put($this->challengeKey($challengeId), $payload, $seconds);
    }

    private function forget(string $challengeId, int $userId): void
    {
        Cache::forget($this->challengeKey($challengeId));
        if (Cache::get($this->userIndexKey($userId)) === $challengeId) {
            Cache::forget($this->userIndexKey($userId));
        }
    }

    private function challengeKey(string $challengeId): string
    {
        return '2fa_challenge_'.$challengeId;
    }

    private function userIndexKey(int $userId): string
    {
        return '2fa_user_'.$userId;
    }
}
