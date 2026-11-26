<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Contracts\LlmClientInterface;
use App\Controller\ChatController;
use App\Entity\Conversation;
use App\Integration\Gemini\GeminiLlmClient;
use App\Repository\ConversationRepository;
use App\Repository\ProductProjectionRepository;
use App\Service\CategoryCatalog;
use App\Service\ChatQuota;
use App\Service\RecommendationEngine;
use Doctrine\ORM\EntityManagerInterface;
use Ecommerce\PlatformKit\Token\AccessTokenSigner;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ChatControllerTest extends WebTestCase
{
    public function testHealthEndpointIsPublicUnderRecommendationsPrefix(): void
    {
        $client = self::createClient();
        $client->request('GET', '/recommendations/health');

        $this->assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame(['status' => 'ok'], $body);
    }

    public function testChatRequiresValidBearerToken(): void
    {
        $client = self::createClient();
        $client->request(
            'POST',
            '/recommendations/chat',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['message' => 'Привет', 'store_id' => 1], \JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(401);
        $this->assertStringContainsString(
            'application/problem+json',
            (string) $client->getResponse()->headers->get('content-type'),
        );
    }

    public function testChatReturns404OnAnotherUsersConversation(): void
    {
        $client = self::createClient();
        $otherUsersConversation = new Conversation(99, 1);

        $conversations = $this->createStub(ConversationRepository::class);
        $conversations->method('find')->willReturn($otherUsersConversation);

        $this->installChatController(
            $this->createStub(LlmClientInterface::class),
            $this->createStub(ProductProjectionRepository::class),
            $conversations,
        );

        $this->postChatJson($client, $this->issueToken(42), [
            'message' => 'Подбери кроссовки',
            'store_id' => 1,
            'conversation_id' => (string) $otherUsersConversation->getId(),
        ]);

        $this->assertResponseStatusCodeSame(404);
        $this->assertStringContainsString(
            'application/problem+json',
            (string) $client->getResponse()->headers->get('content-type'),
        );
        /** @var array<string, mixed> $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame(404, $body['status']);
        $this->assertSame('Диалог не найден.', $body['detail']);
    }

    public function testChatStripsExternalLinksFromLlmReply(): void
    {
        $client = self::createClient();

        $http = new MockHttpClient([
            new MockResponse(json_encode([
                'candidates' => [[
                    'content' => ['parts' => [['text' => '{"category":"Обувь","price_max":15000,"attributes":{}}']]],
                ]],
            ], \JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([
                'embedding' => ['values' => [0.1, 0.2, 0.3]],
            ], \JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => 'Рекомендую Salomon Gore-Tex! Подробнее [тут](https://evil.example/phish) и https://evil.example/raw',
                    ]]],
                ]],
            ], \JSON_THROW_ON_ERROR)),
        ]);

        $products = $this->createStub(ProductProjectionRepository::class);
        $products->method('distinctCategoryNames')->willReturn(['Обувь']);
        $products->method('searchSimilar')->willReturn([[
            'product_id' => 101,
            'name' => 'Salomon Gore-Tex',
            'price' => '14500.00',
            'category' => 'Обувь',
            'attributes' => ['color' => 'черный'],
            'product_url' => '/products/101',
            'similarity' => '0.9200',
        ]]);

        $this->installChatController(
            new GeminiLlmClient($http, new NullLogger(), 'test-key'),
            $products,
            $this->createStub(ConversationRepository::class),
        );

        $this->postChatJson($client, $this->issueToken(17), [
            'message' => 'Нужны зимние кроссовки до 15000',
            'store_id' => 1,
        ]);

        $this->assertResponseIsSuccessful();
        /** @var array<string, mixed> $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertIsString($body['reply']);
        $this->assertStringContainsString('Salomon Gore-Tex', $body['reply']);
        $this->assertStringNotContainsString('evil.example', $body['reply']);
        $this->assertStringNotContainsString('http', $body['reply']);
    }

    public function testChatReturns429WhenMinuteRateQuotaIsExceeded(): void
    {
        $client = self::createClient();
        $client->disableReboot();

        $llm = $this->createStub(LlmClientInterface::class);
        $llm->method('extractFilters')->willReturn([]);
        $llm->method('createEmbedding')->willReturn([0.1, 0.2]);
        $llm->method('generateRecommendation')->willReturn('Ответ консультанта.');

        $products = $this->createStub(ProductProjectionRepository::class);
        $products->method('distinctCategoryNames')->willReturn([]);
        $products->method('searchSimilar')->willReturn([]);

        $storage = new \Symfony\Component\RateLimiter\Storage\InMemoryStorage();
        $quota = new ChatQuota(
            new \Symfony\Component\RateLimiter\RateLimiterFactory(['id' => 'chat_messages', 'policy' => 'sliding_window', 'limit' => 10, 'interval' => '1 minute'], $storage),
            new \Symfony\Component\RateLimiter\RateLimiterFactory(['id' => 'chat_daily', 'policy' => 'sliding_window', 'limit' => 40, 'interval' => '1 day'], $storage),
        );

        $this->installChatController(
            $llm,
            $products,
            $this->createStub(ConversationRepository::class),
            $quota,
        );

        $token = $this->issueToken(555);
        for ($i = 0; $i < 10; ++$i) {
            $this->postChatJson($client, $token, [
                'message' => 'Сообщение '.$i,
                'store_id' => 1,
            ]);
            $this->assertResponseIsSuccessful();
        }

        $this->postChatJson($client, $token, [
            'message' => 'Одиннадцатое сообщение за минуту',
            'store_id' => 1,
        ]);

        $this->assertResponseStatusCodeSame(429);
        $this->assertTrue($client->getResponse()->headers->has('retry-after'));
        $this->assertStringContainsString(
            'application/problem+json',
            (string) $client->getResponse()->headers->get('content-type'),
        );
        /** @var array<string, mixed> $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame(429, $body['status']);
    }

    private function installChatController(
        LlmClientInterface $llm,
        ProductProjectionRepository $products,
        ConversationRepository $conversations,
        ?ChatQuota $quota = null,
    ): void {
        $container = self::getContainer();
        /** @var DenormalizerInterface $denormalizer */
        $denormalizer = $container->get(SerializerInterface::class);
        /** @var ValidatorInterface $validator */
        $validator = $container->get(ValidatorInterface::class);
        if (!$quota instanceof ChatQuota) {
            /** @var ChatQuota $containerQuota */
            $containerQuota = $container->get(ChatQuota::class);
            $quota = $containerQuota;
        }

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('contains')->willReturn(false);
        $em->method('wrapInTransaction')->willReturnCallback(
            static fn (callable $callback): mixed => $callback($em),
        );

        $engine = new RecommendationEngine(
            $llm,
            $products,
            $em,
            new NullLogger(),
            $denormalizer,
            $validator,
            new CategoryCatalog(new ArrayAdapter(), $products),
        );

        $controller = new ChatController($engine, $conversations, $quota);
        $controller->setContainer($container);
        $container->set(ChatController::class, $controller);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postChatJson(KernelBrowser $client, string $token, array $payload): void
    {
        $client->request(
            'POST',
            '/recommendations/chat',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );
    }

    private function issueToken(int $userId): string
    {
        $privateKey = (string) file_get_contents(\dirname(__DIR__, 3).'/packages/platform-kit/tests/fixtures/access-private.pem');
        $signer = new AccessTokenSigner($privateKey, 'test-1', 'http://auth-app', 'ecommerce');

        return $signer->issue($userId, ['customer']);
    }
}
