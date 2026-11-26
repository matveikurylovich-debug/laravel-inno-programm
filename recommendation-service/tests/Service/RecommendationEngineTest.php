<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Contracts\LlmClientInterface;
use App\Controller\ChatController;
use App\Dto\ChatRequest;
use App\Dto\ChatResponse;
use App\Dto\SearchFilters;
use App\Entity\Conversation;
use App\Enum\MessageRole;
use App\Integration\Gemini\GeminiLlmClient;
use App\Repository\ConversationRepository;
use App\Repository\ProductProjectionRepository;
use App\Security\JwtUser;
use App\Service\CategoryCatalog;
use App\Service\ChatQuota;
use App\Service\RecommendationEngine;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use stdClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RecommendationEngineTest extends KernelTestCase
{
    public function testExistingConversationKeepsItsStoreAndSavesBothMessagesInTransaction(): void
    {
        $llm = $this->createStub(LlmClientInterface::class);
        $llm->method('extractFilters')->willReturn([]);
        $llm->method('createEmbedding')->willReturn([0.1, 0.2]);
        $llm->method('generateRecommendation')->willReturn('A recommendation');

        $repository = $this->createMock(ProductProjectionRepository::class);
        $repository->expects($this->once())
            ->method('searchSimilar')
            ->with(
                [0.1, 0.2],
                7,
                self::callback(static fn (mixed $filters): bool => $filters instanceof SearchFilters),
                5,
                0.57,
            )
            ->willReturn([]);
        $repository->method('distinctCategoryNames')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('contains')->willReturn(true);
        $em->expects($this->once())
            ->method('wrapInTransaction')
            ->willReturnCallback(static fn (callable $callback): mixed => $callback($em));
        $em->expects($this->exactly(2))->method('persist');

        $clock = new MockClock('2026-11-25T15:00:00+00:00');
        $conversation = new Conversation(1, 7, new MockClock('2026-11-25T14:50:00+00:00')->now());
        $engine = new RecommendationEngine(
            $llm,
            $repository,
            $em,
            new NullLogger(),
            new Serializer([new ObjectNormalizer()]),
            Validation::createValidator(),
            new CategoryCatalog(new ArrayAdapter(), $repository),
            $clock,
        );
        $result = $engine->processUserMessage($conversation, 'A phone');

        $this->assertSame('A recommendation', $result['reply']);
        $this->assertSame([], $result['filters']->attributes);
        $this->assertCount(2, $conversation->getMessages());
        $messages = $conversation->getMessages()->toArray();
        $this->assertSame(MessageRole::User, $messages[0]->getRole());
        $this->assertSame('A phone', $messages[0]->getContent());
        $this->assertSame('2026-11-25T15:00:00+00:00', $messages[0]->getCreatedAt()->format(\DATE_ATOM));
        $this->assertSame(MessageRole::Assistant, $messages[1]->getRole());
        $this->assertSame('2026-11-25T15:00:00+00:00', $conversation->getLastActivityAt()->format(\DATE_ATOM));
    }

    public function testDenormalizesAndValidatesLlmFiltersWithRealStoreCategories(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var DenormalizerInterface $denormalizer */
        $denormalizer = $container->get(SerializerInterface::class);
        /** @var ValidatorInterface $validator */
        $validator = $container->get(ValidatorInterface::class);

        $llm = $this->createMock(LlmClientInterface::class);
        $llm->expects($this->once())
            ->method('extractFilters')
            ->with('зимние кроссовки до 15000', ['Куртки', 'Обувь'])
            ->willReturn([
                'category' => 'Обувь',
                'price_max' => -50,
                'attributes' => ['color' => 'черный', 'material' => null],
            ]);
        $llm->method('createEmbedding')->willReturn([0.3, 0.4]);
        $llm->method('generateRecommendation')->willReturn('Рекомендую кроссовки.');

        $repository = $this->createMock(ProductProjectionRepository::class);
        $repository->method('distinctCategoryNames')->willReturn(['Обувь', 'Куртки']);
        $repository->expects($this->once())
            ->method('searchSimilar')
            ->with(
                [0.3, 0.4],
                4,
                self::callback(static fn (SearchFilters $filters): bool => 'Обувь' === $filters->category
                    && null === $filters->price_max
                    && $filters->attributes === ['color' => 'черный']),
                5,
                0.57,
            )
            ->willReturn([
                [
                    'product_id' => 101,
                    'name' => 'Salomon',
                    'price' => '14500.00',
                    'category' => 'Обувь',
                    'attributes' => ['color' => 'черный'],
                    'product_url' => '/products/101',
                    'similarity' => '0.9100',
                ],
            ]);

        $em = $this->transactionalEntityManagerStub();
        $engine = new RecommendationEngine(
            $llm,
            $repository,
            $em,
            new NullLogger(),
            $denormalizer,
            $validator,
            new CategoryCatalog(new ArrayAdapter(), $repository),
        );

        $result = $engine->processUserMessage(new Conversation(1, 4), 'зимние кроссовки до 15000');

        $this->assertSame('Обувь', $result['filters']->category);
        $this->assertNull($result['filters']->price_max);
        $this->assertSame(['color' => 'черный'], $result['filters']->attributes);
    }

    public function testFallbackRetriesWithoutSoftConstraintsPreservingPriceMax(): void
    {
        $llm = $this->createStub(LlmClientInterface::class);
        $llm->method('extractFilters')->willReturn([
            'category' => 'Обувь',
            'price_max' => 12000,
            'attributes' => ['color' => 'красный'],
        ]);
        $llm->method('createEmbedding')->willReturn([0.5, 0.5]);
        $llm->method('generateRecommendation')->willReturn('Нашли похожий вариант.');

        /** @var list<SearchFilters> $calls */
        $calls = [];
        $repository = $this->createMock(ProductProjectionRepository::class);
        $repository->method('distinctCategoryNames')->willReturn(['Обувь']);
        $repository->expects($this->exactly(2))
            ->method('searchSimilar')
            ->willReturnCallback(static function (array $embedding, int $storeId, SearchFilters $filters) use (&$calls): array {
                $calls[] = $filters;
                if (1 === \count($calls)) {
                    return [];
                }

                return [[
                    'product_id' => 202,
                    'name' => 'Кроссовки синие',
                    'price' => '9900.00',
                    'category' => 'Спорт',
                    'attributes' => ['color' => 'синий'],
                    'product_url' => '/products/202',
                    'similarity' => '0.8200',
                ]];
            });

        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $engine = new RecommendationEngine(
            $llm,
            $repository,
            $this->transactionalEntityManagerStub(),
            new NullLogger(),
            new Serializer([new ObjectNormalizer()]),
            $validator,
            new CategoryCatalog(new ArrayAdapter(), $repository),
        );

        $result = $engine->processUserMessage(new Conversation(2, 5), 'красные кроссовки до 12000');

        $this->assertCount(2, $calls);
        $this->assertSame('Обувь', $calls[0]->category);
        $this->assertEqualsWithDelta(12000.0, $calls[0]->price_max, \PHP_FLOAT_EPSILON);
        $this->assertSame(['color' => 'красный'], $calls[0]->attributes);

        $this->assertNull($calls[1]->category);
        $this->assertEqualsWithDelta(12000.0, $calls[1]->price_max, \PHP_FLOAT_EPSILON);
        $this->assertSame([], $calls[1]->attributes);
        $this->assertCount(1, $result['products']);
    }

    public function testChatControllerReturnsJsonWithObjectAttributesNotStringsOrLists(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var DenormalizerInterface $denormalizer */
        $denormalizer = $container->get(SerializerInterface::class);
        /** @var ValidatorInterface $validator */
        $validator = $container->get(ValidatorInterface::class);

        $llm = $this->createStub(LlmClientInterface::class);
        $llm->method('extractFilters')->willReturn([]);
        $llm->method('createEmbedding')->willReturn([0.1, 0.2]);
        $llm->method('generateRecommendation')->willReturn('Подборка готова.');

        $repository = $this->createStub(ProductProjectionRepository::class);
        $repository->method('distinctCategoryNames')->willReturn([]);
        $repository->method('searchSimilar')->willReturn([
            [
                'product_id' => 1,
                'name' => 'Базовая футболка',
                'price' => '1500.00',
                'category' => 'Одежда',
                'attributes' => [],
                'product_url' => '/products/1',
                'similarity' => '0.8500',
            ],
            [
                'product_id' => 2,
                'name' => 'Зимняя куртка',
                'price' => '12000.00',
                'category' => 'Куртки',
                'attributes' => '{"color":"черный","material":"мембрана"}',
                'product_url' => '/products/2',
                'similarity' => '0.8100',
            ],
        ]);

        $em = $this->transactionalEntityManagerStub();

        $engine = new RecommendationEngine(
            $llm,
            $repository,
            $em,
            new NullLogger(),
            $denormalizer,
            $validator,
            new CategoryCatalog(new ArrayAdapter(), $repository),
        );

        $storage = new InMemoryStorage();
        $quota = new ChatQuota(
            new RateLimiterFactory(['id' => 'minute', 'policy' => 'sliding_window', 'limit' => 10, 'interval' => '1 minute'], $storage),
            new RateLimiterFactory(['id' => 'daily', 'policy' => 'sliding_window', 'limit' => 40, 'interval' => '1 day'], $storage),
        );

        $controller = new ChatController(
            $engine,
            $this->createStub(ConversationRepository::class),
            $quota,
        );
        $controller->setContainer($container);

        $httpResponse = $controller->chat(
            new ChatRequest(message: 'покажи одежду', store_id: 1),
            new JwtUser(42, 'buyer@example.com', ['ROLE_USER']),
        );

        $decoded = json_decode((string) $httpResponse->getContent(), false, 512, \JSON_THROW_ON_ERROR);
        $this->assertInstanceOf(stdClass::class, $decoded);
        $this->assertInstanceOf(stdClass::class, $decoded->products[0]->attributes);
        $this->assertSame([], get_object_vars($decoded->products[0]->attributes));
        $this->assertInstanceOf(stdClass::class, $decoded->products[1]->attributes);
        $this->assertSame('черный', $decoded->products[1]->attributes->color);
        $this->assertInstanceOf(stdClass::class, $decoded->filters->attributes);
        $this->assertObjectNotHasProperty('softConstraints', $decoded->filters);

        $dto = new ChatResponse('conv-1', 'ok', [['product_id' => 1, 'attributes' => '{}']], new SearchFilters());
        $this->assertInstanceOf(stdClass::class, $dto->products[0]['attributes']);
    }

    public function testGeminiExtractFiltersPassesCategoryEnumInResponseSchema(): void
    {
        /** @var list<array<string, mixed>> $capturedPayload */
        $capturedPayload = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedPayload): MockResponse {
            $body = \is_string($options['body'] ?? null) ? $options['body'] : '{}';
            /** @var array<string, mixed> $decodedBody */
            $decodedBody = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
            $capturedPayload[] = $decodedBody;

            return new MockResponse(json_encode([
                'candidates' => [[
                    'content' => [
                        'parts' => [['text' => '{"category":"Обувь","price_max":10000,"attributes":{"color":"черный"}}']],
                    ],
                ]],
            ], \JSON_THROW_ON_ERROR));
        });

        $client = new GeminiLlmClient($http, new NullLogger(), 'test-key');
        $parsed = $client->extractFilters('черные ботинки до 10000', ['Обувь', 'Куртки', 'Обувь', ' ']);
        $client->extractFilters('что угодно', []);

        $this->assertSame('Обувь', $parsed['category']);
        /** @var array<string, array<string, array<string, mixed>>> $config0 */
        $config0 = $capturedPayload[0]['generationConfig'];
        /** @var array<string, array<string, array<string, mixed>>> $config1 */
        $config1 = $capturedPayload[1]['generationConfig'];
        $this->assertSame(['type' => 'STRING', 'nullable' => true, 'enum' => ['Куртки', 'Обувь']], $config0['responseSchema']['properties']['category']);
        $this->assertSame(['type' => 'STRING', 'nullable' => true], $config1['responseSchema']['properties']['category']);
    }

    private function transactionalEntityManagerStub(): EntityManagerInterface
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('contains')->willReturn(false);
        $em->method('wrapInTransaction')->willReturnCallback(
            static fn (callable $callback): mixed => $callback($em),
        );

        return $em;
    }
}
