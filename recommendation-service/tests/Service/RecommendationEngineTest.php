<?php
namespace App\Tests\Service;

use App\Contract\LlmClientInterface;
use App\Entity\Conversation;
use App\Repository\ProductProjectionRepository;
use App\Service\RecommendationEngine;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RecommendationEngineTest extends KernelTestCase
{
    public function test_existing_conversation_keeps_its_store_and_saves_both_messages(): void
    {
        $llm = $this->createStub(LlmClientInterface::class);
        $llm->method('extractFilters')->willReturn([]);
        $llm->method('createEmbedding')->willReturn([0.1, 0.2]);
        $llm->method('generateRecommendation')->willReturn('A recommendation');
        $repository = $this->createMock(ProductProjectionRepository::class);
        $repository->expects(self::once())->method('searchSimilar')->with([0.1, 0.2], 7, [], 5, 0.57)->willReturn([]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::exactly(2))->method('persist');
        $em->expects(self::once())->method('flush');
        $conversation = new Conversation(1, 7);
        $engine = new RecommendationEngine($llm, $repository, $em, new NullLogger());
        $result = $engine->processUserMessage($conversation, 'A phone', 999);
        self::assertSame('A recommendation', $result['reply']);
        self::assertCount(2, $conversation->getMessages());
        self::assertSame('A phone', $conversation->getMessages()->first()->getContent());
    }
}
