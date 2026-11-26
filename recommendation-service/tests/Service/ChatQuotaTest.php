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
            $this->factory('chat_messages', 10, '1 minute'),
            $this->factory('chat_daily', 40, '1 day'),
            $clock,
        );

        for ($i = 0; $i < 10; ++$i) {
            $this->assertNull($quota->consume(7));
        }

        $this->assertNotNull($quota->consume(7));
    }

    public function testExternalLinksAreRemovedFromTheAssistantReply(): void
    {
        $clean = AssistantReply::sanitize('Смотрите [куртку](https://evil.example/a) и //evil.example/b');

        $this->assertStringNotContainsString('evil.example', $clean);
        $this->assertStringNotContainsString('http', $clean);
    }

    private function factory(string $id, int $limit, string $interval): RateLimiterFactory
    {
        return new RateLimiterFactory([
            'id' => $id,
            'policy' => 'sliding_window',
            'limit' => $limit,
            'interval' => $interval,
        ], new InMemoryStorage());
    }
}
