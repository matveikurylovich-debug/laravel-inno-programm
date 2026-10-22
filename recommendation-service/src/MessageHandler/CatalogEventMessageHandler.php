<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Contract\LlmClientInterface;
use App\Entity\ProductProjection;
use App\Message\CatalogEventMessage;
use App\Repository\ProductProjectionRepository;
use App\Service\ProductTextBuilder;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
final class CatalogEventMessageHandler
{
    public function __construct(
        private readonly ProductProjectionRepository $repository,
        private readonly ProductTextBuilder $textBuilder,
        private readonly LlmClientInterface $llmClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(CatalogEventMessage $message): void
    {
        $productId = $message->getProductId();
        if ($productId <= 0) {
            $this->logger->warning('Событие каталога без product id пропущено', [
                'event' => $message->eventType,
            ]);

            return;
        }

        if ($message->eventType === 'product.deleted') {
            $this->repository->deleteByProductId($productId);
            $this->logger->info('Проекция товара удалена', ['product_id' => $productId]);

            return;
        }

        if (!in_array($message->eventType, ['product.created', 'product.updated', 'product.published', 'product.unpublished', 'stock.changed'], true)) {
            $this->logger->info('Событие каталога не требует индексации', ['event' => $message->eventType]);

            return;
        }

        $data = $this->projectionData($message, $productId);
        if ($data === null) {
            return;
        }

        $text = $this->textBuilder->build(
            $data['name'],
            $data['description'],
            $data['category'],
            $data['attributes'],
        );

        try {
            $data['embedding'] = $this->llmClient->createEmbedding($text);
        } catch (Throwable $exception) {
            $this->logger->error('Не удалось посчитать эмбеддинг товара из события каталога', [
                'product_id' => $productId,
                'event' => $message->eventType,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $this->repository->upsertProjection($data);
        $this->logger->info('Проекция товара обновлена из события каталога', [
            'product_id' => $productId,
            'event' => $message->eventType,
        ]);
    }

    /**
     * @return array{
     *     product_id: int,
     *     store_id: int,
     *     name: string,
     *     description: ?string,
     *     category: string,
     *     price: float|int|string,
     *     is_published: bool,
     *     is_in_stock: bool,
     *     product_url: string,
     *     attributes: array<string, mixed>
     * }|null
     */
    private function projectionData(CatalogEventMessage $message, int $productId): ?array
    {
        $payload = $message->payload;
        $existing = $this->repository->find($productId);

        $name = $this->stringOf($payload, 'name') ?? ($existing instanceof ProductProjection ? $existing->getName() : null);
        if ($name === null || $name === '') {
            $this->logger->warning('Недостаточно данных для индексации товара', [
                'product_id' => $productId,
                'event' => $message->eventType,
            ]);

            return null;
        }

        $attributes = $payload['attributes'] ?? null;
        if (!is_array($attributes)) {
            $attributes = $existing instanceof ProductProjection ? $existing->getAttributes() : [];
        }

        return [
            'product_id' => $productId,
            'store_id' => (int) ($payload['store_id'] ?? ($existing instanceof ProductProjection ? $existing->getStoreId() : 0)),
            'name' => $name,
            'description' => $this->stringOf($payload, 'description') ?? ($existing instanceof ProductProjection ? $existing->getDescription() : null),
            'category' => $this->stringOf($payload, 'category')
                ?? $this->stringOf($payload, 'category_name')
                ?? ($existing instanceof ProductProjection ? $existing->getCategoryName() : ''),
            'price' => $payload['price'] ?? ($existing instanceof ProductProjection ? $existing->getPrice() : '0'),
            'is_published' => $this->published($message->eventType, $payload, $existing),
            'is_in_stock' => $this->inStock($payload, $existing),
            'product_url' => $this->stringOf($payload, 'product_url')
                ?? ($existing instanceof ProductProjection ? $existing->getProductUrl() : sprintf('/products/%d', $productId)),
            'attributes' => $attributes,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function published(string $event, array $payload, ?ProductProjection $existing): bool
    {
        if ($event === 'product.published') {
            return true;
        }

        if ($event === 'product.unpublished') {
            return false;
        }

        if (array_key_exists('is_published', $payload)) {
            return (bool) $payload['is_published'];
        }

        if (array_key_exists('is_active', $payload)) {
            return (bool) $payload['is_active'];
        }

        return $existing instanceof ProductProjection ? $existing->isPublished() : true;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function inStock(array $payload, ?ProductProjection $existing): bool
    {
        if (array_key_exists('is_in_stock', $payload)) {
            return (bool) $payload['is_in_stock'];
        }

        if (array_key_exists('available', $payload)) {
            return (int) $payload['available'] > 0;
        }

        if (array_key_exists('quantity', $payload)) {
            $reserved = (int) ($payload['reserved_quantity'] ?? 0);

            return ((int) $payload['quantity'] - $reserved) > 0;
        }

        return $existing instanceof ProductProjection ? $existing->isInStock() : true;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function stringOf(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
