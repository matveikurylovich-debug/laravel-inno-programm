<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final readonly class ChatQuota
{
    public function __construct(
        #[Autowire(service: 'limiter.chat_messages')]
        private RateLimiterFactory $chatMessagesLimiter,
        #[Autowire(service: 'limiter.chat_daily')]
        private RateLimiterFactory $chatDailyLimiter,
        private ClockInterface $clock = new NativeClock(),
    ) {
    }

    /**
     * @return int|null Retry-After seconds when the quota is exhausted
     */
    public function consume(int $userId): ?int
    {
        $key = (string) $userId;
        $nowTimestamp = $this->clock->now()->getTimestamp();

        $minute = $this->chatMessagesLimiter->create($key)->consume(1);
        if (!$minute->isAccepted()) {
            return max(1, $minute->getRetryAfter()->getTimestamp() - $nowTimestamp);
        }

        $daily = $this->chatDailyLimiter->create($key)->consume(1);
        if (!$daily->isAccepted()) {
            return max(1, $daily->getRetryAfter()->getTimestamp() - $nowTimestamp);
        }

        return null;
    }
}
