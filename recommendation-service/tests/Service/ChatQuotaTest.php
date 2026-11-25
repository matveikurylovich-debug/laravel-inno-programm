<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\AssistantReply;
use App\Service\ChatQuota;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class ChatQuotaTest extends TestCase
{
    public function testEleventhMessageInAMinuteIsRejected(): void
    {
        $clock = new MockClock('2026-11-25 12:00:00');
        $quota = new ChatQuota(
            $this->factory('chat_messages', 10, '1 minute', $clock),
            $this->factory('chat_daily', 40, '1 day', $clock),
        );

        for ($i = 0; $i < 10; $i++) {
            self::assertNull($quota->consume(7));
        }

        self::assertNotNull($quota->consume(7));
    }

    public function testExternalLinksAreRemovedFromTheAssistantReply(): void
    {
        $clean = AssistantReply::sanitize('Смотрите [куртку](https://evil.example/a) и //evil.example/b');

        self::assertStringNotContainsString('evil.example', $clean);
        self::assertStringNotContainsString('http', $clean);
    }

    private function factory(string $id, int $limit, string $interval, MockClock $clock): RateLimiterFactory
    {
        return new RateLimiterFactory([
            'id' => $id,
            'policy' => 'sliding_window',
            'limit' => $limit,
            'interval' => $interval,
        ], new InMemoryStorage(), null, $clock);
    }
}
