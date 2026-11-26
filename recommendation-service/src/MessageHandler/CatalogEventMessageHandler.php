<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Dto\CatalogProductEvent;
use App\Entity\ProductProjection;
use App\Message\CatalogEventMessage;
use App\Repository\ProductProjectionRepository;
use App\Service\CategoryCatalog;
use App\Service\ProductIndexer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
final readonly class CatalogEventMessageHandler
{
    public function __construct(
        private ProductProjectionRepository $repository,
        private ProductIndexer $productIndexer,
        private LoggerInterface $logger,
        private CategoryCatalog $categories,
    ) {
    }

    public function __invoke(CatalogEventMessage $message): void
    {
        if (\in_array($message->eventType, ['category.created', 'category.updated', 'category.deleted'], true)) {
            $this->categories->refresh($message);
            $this->logger->info('Список категорий магазина обновлён', [
                'event' => $message->eventType,
            ]);

            return;
        }

        $productId = $message->getProductId();
        if ($productId <= 0) {
            $this->logger->warning('Событие каталога без product id пропущено', [
                'event' => $message->eventType,
            ]);

            return;
        }

        if ('product.deleted' === $message->eventType) {
            $this->repository->deleteByProductId($productId);
            $this->logger->info('Проекция товара удалена', ['product_id' => $productId]);

            return;
        }

        if ('stock.changed' === $message->eventType) {
            $available = $message->payload['available'] ?? null;
            $this->repository->updateAvailability($productId, is_numeric($available) && (int) $available > 0);
            $this->logger->info('Наличие товара обновлено без пересчёта эмбеддинга', [
                'product_id' => $productId,
            ]);

            return;
        }

        if (!\in_array($message->eventType, ['product.created', 'product.updated', 'product.published', 'product.unpublished'], true)) {
            $this->logger->info('Событие каталога не требует индексации', ['event' => $message->eventType]);

            return;
        }

        $data = $this->projectionData($message, $productId);
        if (!$data instanceof CatalogProductEvent) {
            return;
        }

        try {
            $this->productIndexer->index($data);
        } catch (Throwable $exception) {
            $this->logger->error('Не удалось посчитать эмбеддинг товара из события каталога', [
                'product_id' => $productId,
                'event' => $message->eventType,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $this->logger->info('Проекция товара обновлена из события каталога', [
            'product_id' => $productId,
            'event' => $message->eventType,
        ]);
    }

    private function projectionData(CatalogEventMessage $message, int $productId): ?CatalogProductEvent
    {
        $payload = $message->payload;
        $existing = $this->repository->find($productId);

        $name = $this->stringOf($payload, 'name') ?? ($existing instanceof ProductProjection ? $existing->getName() : null);
        if (null === $name || '' === $name) {
            $this->logger->warning('Недостаточно данных для индексации товара', [
                'product_id' => $productId,
                'event' => $message->eventType,
            ]);

            return null;
        }

        $rawAttributes = $payload['attributes'] ?? null;
        if (\is_array($rawAttributes)) {
            $attributes = [];
            foreach ($rawAttributes as $attrKey => $attrVal) {
                if (\is_string($attrKey)) {
                    $attributes[$attrKey] = $attrVal;
                }
            }
        } else {
            $attributes = $existing instanceof ProductProjection ? $existing->getAttributes() : [];
        }

        $rawStoreId = $payload['store_id'] ?? ($existing instanceof ProductProjection ? $existing->getStoreId() : 0);
        $storeId = is_numeric($rawStoreId) ? (int) $rawStoreId : 0;

        return new CatalogProductEvent(
            product_id: $productId,
            store_id: $storeId,
            name: $name,
            description: $this->stringOf($payload, 'description') ?? ($existing instanceof ProductProjection ? $existing->getDescription() : null),
            category: $this->stringOf($payload, 'category')
                ?? $this->stringOf($payload, 'category_name')
                ?? ($existing instanceof ProductProjection ? $existing->getCategoryName() : ''),
            price: $this->priceOf($payload, $existing),
            is_published: $this->published($message->eventType, $payload, $existing),
            is_in_stock: $this->inStock($payload, $existing),
            product_url: $this->stringOf($payload, 'product_url')
                ?? ($existing instanceof ProductProjection ? $existing->getProductUrl() : \sprintf('/products/%d', $productId)),
            attributes: $attributes,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function priceOf(array $payload, ?ProductProjection $existing): float
    {
        $price = $payload['price'] ?? ($existing instanceof ProductProjection ? $existing->getPrice() : 0);

        return is_numeric($price) ? (float) $price : 0.0;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function published(string $event, array $payload, ?ProductProjection $existing): bool
    {
        if ('product.published' === $event) {
            return true;
        }

        if ('product.unpublished' === $event) {
            return false;
        }

        if (\array_key_exists('is_published', $payload)) {
            return filter_var($payload['is_published'], \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE) ?? (bool) $payload['is_published'];
        }

        if (\array_key_exists('is_active', $payload)) {
            return filter_var($payload['is_active'], \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE) ?? (bool) $payload['is_active'];
        }

        return $existing instanceof ProductProjection ? $existing->isPublished() : true;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function inStock(array $payload, ?ProductProjection $existing): bool
    {
        if (\array_key_exists('is_in_stock', $payload)) {
            return (bool) $payload['is_in_stock'];
        }

        if (\array_key_exists('available', $payload)) {
            $available = $payload['available'];

            return is_numeric($available) && (int) $available > 0;
        }

        if (\array_key_exists('quantity', $payload)) {
            $quantity = is_numeric($payload['quantity']) ? (int) $payload['quantity'] : 0;
            $rawReserved = $payload['reserved_quantity'] ?? 0;
            $reserved = is_numeric($rawReserved) ? (int) $rawReserved : 0;

            return ($quantity - $reserved) > 0;
        }

        return $existing instanceof ProductProjection ? $existing->isInStock() : true;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function stringOf(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return \is_string($value) ? $value : null;
    }
}
