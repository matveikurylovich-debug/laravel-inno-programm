<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Events\TwoFactorCodeRequested;
use App\Exceptions\InvalidTwoFactorChallengeException;
use App\Exceptions\TwoFactorRateLimitException;
use App\Models\User;
use App\Support\TwoFactorCacheKey;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Str;

class TwoFactorService
{
    public const int TTL_MINUTES = TwoFactorCacheKey::TTL_MINUTES;

    public const int MAX_ATTEMPTS = 5;

    public const int MAX_RESENDS = 3;

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly EventDispatcher $events,
    ) {}

    /**
     * @return array{challenge_id: string, expires_in: int}
     */
    public function challenge(User $user): array
    {
        $userId = (int) $user->id;
        $previous = $this->cache->get($this->userIndexKey($userId));
        if (is_string($previous) && $previous !== '') {
            $this->forget($previous, $userId);
        }

        $challengeId = Str::random(40);
        $code = (string) random_int(100000, 999999);
        $now = CarbonImmutable::now();
        $expiresAt = $now->addMinutes(self::TTL_MINUTES)->getTimestamp();
        $ttl = max(1, $expiresAt - $now->getTimestamp());

        $this->cache->put($this->challengeKey($challengeId), [
            'user_id' => $userId,
            'code_hash' => hash('sha256', $code),
            'expires_at' => $expiresAt,
        ], $ttl);
        $this->cache->put($this->attemptsKey($challengeId), 0, $ttl);
        $this->cache->put($this->resendKey($challengeId), 0, $ttl);
        $this->cache->put($this->userIndexKey($userId), $challengeId, $ttl);

        $this->events->dispatch(new TwoFactorCodeRequested($user, $code, self::TTL_MINUTES));

        return [
            'challenge_id' => $challengeId,
            'expires_in' => self::TTL_MINUTES * 60,
        ];
    }

    public function verify(string $challengeId, string $code): User
    {
        if ((bool) $this->cache->get($this->lockedKey($challengeId))) {
            throw TwoFactorRateLimitException::tooManyAttempts();
        }

        $payload = $this->payload($challengeId);
        if ($payload === null) {
            throw InvalidTwoFactorChallengeException::invalidCode();
        }

        $attempts = (int) $this->cache->increment($this->attemptsKey($challengeId));
        if ($attempts > self::MAX_ATTEMPTS) {
            $this->lock($challengeId, $payload['user_id']);

            throw TwoFactorRateLimitException::tooManyAttempts();
        }

        if (! hash_equals($payload['code_hash'], hash('sha256', $code))) {
            throw InvalidTwoFactorChallengeException::invalidCode();
        }

        $this->forget($challengeId, $payload['user_id']);

        return User::query()->findOrFail($payload['user_id']);
    }

    /**
     * @return array{challenge_id: string, expires_in: int}
     */
    public function resend(string $challengeId): array
    {
        if ((bool) $this->cache->get($this->lockedKey($challengeId))) {
            throw TwoFactorRateLimitException::tooManyAttempts();
        }

        $payload = $this->payload($challengeId);
        if ($payload === null) {
            throw InvalidTwoFactorChallengeException::missingChallenge();
        }

        $resent = (int) $this->cache->increment($this->resendKey($challengeId));
        if ($resent > self::MAX_RESENDS) {
            throw TwoFactorRateLimitException::tooManyResends();
        }

        $code = (string) random_int(100000, 999999);
        $payload['code_hash'] = hash('sha256', $code);
        $this->store($challengeId, $payload);

        $user = User::query()->findOrFail($payload['user_id']);
        $this->events->dispatch(new TwoFactorCodeRequested($user, $code, self::TTL_MINUTES));

        return [
            'challenge_id' => $challengeId,
            'expires_in' => max(1, $payload['expires_at'] - CarbonImmutable::now()->getTimestamp()),
        ];
    }

    /**
     * @return array{user_id: int, code_hash: string, expires_at: int}|null
     */
    private function payload(string $challengeId): ?array
    {
        $payload = $this->cache->get($this->challengeKey($challengeId));
        if (! is_array($payload) || ! isset($payload['user_id'], $payload['code_hash'], $payload['expires_at'])) {
            return null;
        }

        if (! is_numeric($payload['user_id']) || ! is_string($payload['code_hash']) || ! is_numeric($payload['expires_at'])) {
            return null;
        }

        $userId = (int) $payload['user_id'];
        $expiresAt = (int) $payload['expires_at'];

        if ($expiresAt <= CarbonImmutable::now()->getTimestamp()) {
            $this->forget($challengeId, $userId);

            return null;
        }

        return [
            'user_id' => $userId,
            'code_hash' => $payload['code_hash'],
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * @param  array{user_id: int, code_hash: string, expires_at: int}  $payload
     */
    private function store(string $challengeId, array $payload): void
    {
        $seconds = $payload['expires_at'] - CarbonImmutable::now()->getTimestamp();
        if ($seconds <= 0) {
            return;
        }

        $this->cache->put($this->challengeKey($challengeId), $payload, $seconds);
    }

    private function lock(string $challengeId, int $userId): void
    {
        $this->forget($challengeId, $userId);
        $this->cache->put($this->lockedKey($challengeId), true, CarbonImmutable::now()->addMinutes(self::TTL_MINUTES));
    }

    private function forget(string $challengeId, int $userId): void
    {
        $this->cache->forget($this->challengeKey($challengeId));
        $this->cache->forget($this->attemptsKey($challengeId));
        $this->cache->forget($this->resendKey($challengeId));
        if ($this->cache->get($this->userIndexKey($userId)) === $challengeId) {
            $this->cache->forget($this->userIndexKey($userId));
        }
    }

    private function challengeKey(string $challengeId): string
    {
        return TwoFactorCacheKey::Challenge->with($challengeId);
    }

    private function attemptsKey(string $challengeId): string
    {
        return TwoFactorCacheKey::Attempts->with($challengeId);
    }

    private function resendKey(string $challengeId): string
    {
        return TwoFactorCacheKey::Resend->with($challengeId);
    }

    private function lockedKey(string $challengeId): string
    {
        return TwoFactorCacheKey::Locked->with($challengeId);
    }

    private function userIndexKey(int $userId): string
    {
        return TwoFactorCacheKey::User->with($userId);
    }
}
