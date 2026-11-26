<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Contracts\LlmClientInterface;
use App\Dto\CatalogProductEvent;
use App\Integration\Gemini\GeminiLlmClient;
use App\Message\CatalogEventMessage;
use App\MessageHandler\CatalogEventMessageHandler;
use App\Repository\ProductProjectionRepository;
use App\Serializer\CatalogEventSerializer;
use App\Service\CategoryCatalog;
use App\Service\ProductIndexer;
use App\Service\ProductTextBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Component\HttpClient\RetryableHttpClient;

final class CatalogEventMessageHandlerTest extends TestCase
{
    public function testStockChangedUpdatesAvailabilityWithoutCallingGemini(): void
    {
        $http = new MockHttpClient(static function (): never {
            throw new RuntimeException('Gemini must not be called for stock.changed');
        });
        $repository = $this->createMock(ProductProjectionRepository::class);
        $repository->expects($this->once())->method('updateAvailability')->with(4, false);
        $repository->expects($this->never())->method('upsertProjection');

        $llm = new GeminiLlmClient($http, new NullLogger(), 'test-key');
        $handler = new CatalogEventMessageHandler(
            $repository,
            new ProductIndexer(new ProductTextBuilder(), $llm, $repository),
            new NullLogger(),
            $this->categories($repository),
        );

        $handler(new CatalogEventMessage('stock.changed', [
            'product_id' => 4,
            'available' => 0,
            'locations' => [
                ['location_id' => 1, 'available' => 0],
            ],
        ]));

        $this->assertSame(0, $http->getRequestsCount());
    }

    public function testProductDeletedRemovesProjectionWithoutCallingGemini(): void
    {
        $llm = $this->createMock(LlmClientInterface::class);
        $llm->expects($this->never())->method('createEmbedding');

        $repository = $this->createMock(ProductProjectionRepository::class);
        $repository->expects($this->once())->method('deleteByProductId')->with(12);

        $handler = new CatalogEventMessageHandler(
            $repository,
            new ProductIndexer(new ProductTextBuilder(), $llm, $repository),
            new NullLogger(),
            $this->categories($repository),
        );

        $handler(new CatalogEventMessage('product.deleted', ['product_id' => 12]));
    }

    public function testCategoryUpdatedRefreshesCatalogWithoutProductWrites(): void
    {
        $repository = $this->createMock(ProductProjectionRepository::class);
        $repository->expects($this->never())->method('upsertProjection');
        $repository->expects($this->never())->method('updateAvailability');
        $repository->expects($this->never())->method('deleteByProductId');
        $repository->expects($this->atLeastOnce())
            ->method('distinctCategoryNames')
            ->with(3)
            ->willReturn(['Одежда']);

        $categories = $this->categories($repository);
        $llm = $this->createStub(LlmClientInterface::class);
        $handler = new CatalogEventMessageHandler(
            $repository,
            new ProductIndexer(new ProductTextBuilder(), $llm, $repository),
            new NullLogger(),
            $categories,
        );

        $handler(new CatalogEventMessage('category.updated', [
            'store_id' => 3,
            'name' => 'Обувь',
        ]));

        $this->assertSame(['Обувь', 'Одежда'], $categories->names(3));

        $handler(new CatalogEventMessage('category.updated', [
            'store_id' => 3,
            'old_name' => 'Обувь',
            'category_name' => 'Кроссовки',
        ]));

        $this->assertSame(['Кроссовки', 'Одежда'], $categories->names(3));
    }

    public function testProductUpdatedMapsToReadonlyCatalogProductEventDto(): void
    {
        $llm = $this->createStub(LlmClientInterface::class);
        $llm->method('createEmbedding')->willReturn([0.1, 0.2, 0.3]);

        $repository = $this->createMock(ProductProjectionRepository::class);
        $repository->expects($this->once())
            ->method('upsertProjection')
            ->with(self::callback(static fn (CatalogProductEvent $dto): bool => 15 === $dto->product_id
                && 2 === $dto->store_id
                && 'Обувь' === $dto->category
                && false === $dto->is_published
                && $dto->is_in_stock
                && $dto->embedding === [0.1, 0.2, 0.3]));

        $handler = new CatalogEventMessageHandler(
            $repository,
            new ProductIndexer(new ProductTextBuilder(), $llm, $repository),
            new NullLogger(),
            $this->categories($repository),
        );

        $handler(new CatalogEventMessage('product.updated', [
            'product_id' => 15,
            'store_id' => 2,
            'name' => 'Кроссовки',
            'description' => 'Зимние',
            'category_name' => 'Обувь',
            'price' => '9990.00',
            'is_active' => 'false',
            'available' => 3,
            'attributes' => ['color' => 'черный'],
        ]));
    }

    public function testGoldenProductCreatedContractFixtureIndexesCleanly(): void
    {
        $raw = (string) file_get_contents(\dirname(__DIR__, 3).'/contracts/catalog.events/product.created.json');
        $message = new CatalogEventSerializer()->decode(['body' => $raw])->getMessage();
        $this->assertInstanceOf(CatalogEventMessage::class, $message);

        $llm = $this->createStub(LlmClientInterface::class);
        $llm->method('createEmbedding')->willReturn([0.4, 0.5]);

        $repository = $this->createMock(ProductProjectionRepository::class);
        $repository->expects($this->once())
            ->method('upsertProjection')
            ->with(self::callback(static fn (CatalogProductEvent $dto): bool => 10 === $dto->product_id
                && 1 === $dto->store_id
                && 'Смартфон X' === $dto->name
                && 'Смартфоны' === $dto->category
                && 600.0 === $dto->price
                && $dto->is_published
                && $dto->is_in_stock
                && $dto->attributes === ['color' => 'black']
                && $dto->embedding === [0.4, 0.5]));

        $handler = new CatalogEventMessageHandler(
            $repository,
            new ProductIndexer(new ProductTextBuilder(), $llm, $repository),
            new NullLogger(),
            $this->categories($repository),
        );

        $handler($message);
    }

    public function testEmbeddingRetriesA503AndThenAccepts200(): void
    {
        $vector = [0.2, 0.2, 0.2];
        $http = new MockHttpClient([
            new MockResponse('', ['http_code' => 503]),
            new MockResponse(json_encode(['embedding' => ['values' => $vector]], \JSON_THROW_ON_ERROR), ['http_code' => 200]),
        ]);
        $client = new RetryableHttpClient($http, new GenericRetryStrategy([503], 0, 1.0, 0), 2);

        $values = new GeminiLlmClient($client, new NullLogger(), 'test-key')->createEmbedding('phone', 'RETRIEVAL_DOCUMENT');

        $this->assertSame($vector, $values);
        $this->assertSame(2, $http->getRequestsCount());
    }

    private function categories(ProductProjectionRepository $repository): CategoryCatalog
    {
        return new CategoryCatalog(new ArrayAdapter(), $repository);
    }
}
