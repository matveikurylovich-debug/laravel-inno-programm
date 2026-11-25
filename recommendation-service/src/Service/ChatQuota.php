<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\RateLimiter\RateLimiterFactory;

final class ChatQuota
{
    public function __construct(
        private readonly RateLimiterFactory $chatMessagesLimiter,
        private readonly RateLimiterFactory $chatDailyLimiter,
    ) {
    }

    /**
     * @return int|null Retry-After seconds when the quota is exhausted
     */
    public function consume(int $userId): ?int
    {
        $key = (string) $userId;
        $minute = $this->chatMessagesLimiter->create($key)->consume(1);
        if (!$minute->isAccepted()) {
            return max(1, $minute->getRetryAfter()->getTimestamp() - time());
        }

        $daily = $this->chatDailyLimiter->create($key)->consume(1);
        if (!$daily->isAccepted()) {
            return max(1, $daily->getRetryAfter()->getTimestamp() - time());
        }

        return null;
    }
}
