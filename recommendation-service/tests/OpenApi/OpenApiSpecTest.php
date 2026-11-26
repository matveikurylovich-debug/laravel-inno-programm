<?php

declare(strict_types=1);

namespace App\Tests\OpenApi;

use App\Command\CleanupConversationsCommand;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use OpenApi\Generator;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class OpenApiSpecTest extends TestCase
{
    public function testOpenApiSpecDocumentsAllRecommendationRoutes(): void
    {
        $openapi = new Generator(new NullLogger())->generate([\dirname(__DIR__, 2).'/src/OpenApi']);
        $this->assertInstanceOf(\OpenApi\Annotations\OpenApi::class, $openapi);
        $json = json_decode($openapi->toJson(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertIsArray($json);
        $this->assertIsArray($json['paths']);
        $this->assertArrayHasKey('/recommendations/health', $json['paths']);
        $this->assertArrayHasKey('/recommendations/chat', $json['paths']);
        $this->assertArrayHasKey('/recommendations/conversations', $json['paths']);
        $this->assertArrayHasKey('/recommendations/conversations/{id}', $json['paths']);
    }

    public function testCleanupConversationsCommandUsesClockAndConfigurableTtl(): void
    {
        $query = $this->createStub(Query::class);
        $query->method('execute')->willReturn(3);

        /** @var array<string, mixed> $params */
        $params = [];
        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('delete')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnCallback(static function (string $key, mixed $value) use (&$params, $qb): QueryBuilder {
            $params[$key] = $value;

            return $qb;
        });
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        $clock = new MockClock('2026-11-25T18:30:00+00:00');
        $tester = new CommandTester(new CleanupConversationsCommand($em, $clock, 45));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('Deleted 3 expired conversations.', $tester->getDisplay());
        $this->assertInstanceOf(DateTimeImmutable::class, $params['now']);
        $this->assertInstanceOf(DateTimeImmutable::class, $params['cutoff']);
        $this->assertSame('2026-11-25T18:30:00+00:00', $params['now']->format(\DATE_ATOM));
        $this->assertSame('2026-11-25T17:45:00+00:00', $params['cutoff']->format(\DATE_ATOM));
    }
}
