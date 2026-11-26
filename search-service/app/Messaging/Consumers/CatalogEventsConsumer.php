<?php

declare(strict_types=1);

namespace App\Messaging\Consumers;

use App\Contracts\ProductIndex;
use App\Enums\CatalogEventType;
use App\Exceptions\NonRetryableException;
use App\Services\Search\ProductDocument;
use Ecommerce\PlatformKit\Messaging\EventEnvelope;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\MessageConsumer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class CatalogEventsConsumer
{
    public function __construct(
        private readonly ProductIndex $index,
        private readonly ProductDocument $documents,
        private readonly LoggerInterface $logger = new NullLogger,
    ) {}

    public function __invoke(ConsumerMessage $message, MessageConsumer $consumer): void
    {
        $body = $this->body($message);
        $headers = $message->getHeaders();
        $rawEvent = (is_array($headers) ? ($headers['event-type'] ?? null) : null) ?? ($body['event'] ?? null);
        $event = is_string($rawEvent) ? CatalogEventType::tryFrom($rawEvent) : null;

        match ($event) {
            CatalogEventType::ProductCreated, CatalogEventType::ProductUpdated => $this->upsert($body),
            CatalogEventType::ProductDeleted => $this->delete($body),
            CatalogEventType::CategoryCreated, CatalogEventType::CategoryUpdated => $this->renameCategory($body),
            CatalogEventType::CategoryDeleted => $this->clearCategory($body),
            CatalogEventType::StockChanged => $this->stock($body),
            null => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsert(array $payload): void
    {
        $id = $this->productId($payload);

        if ($id === '') {
            return;
        }

        if (array_key_exists('is_active', $payload) && filter_var($payload['is_active'], FILTER_VALIDATE_BOOLEAN) === false) {
            $this->index->delete($id);

            return;
        }

        $this->index->upsert($id, $this->documents->fromProductEvent($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function delete(array $payload): void
    {
        $id = $this->productId($payload);

        if ($id === '') {
            return;
        }

        $this->index->delete($id);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renameCategory(array $payload): void
    {
        $rawCategoryId = $payload['category_id'] ?? 0;
        $categoryId = is_numeric($rawCategoryId) ? (int) $rawCategoryId : 0;
        $rawName = $payload['category_name'] ?? $payload['name'] ?? '';
        $name = is_scalar($rawName) ? (string) $rawName : '';

        if ($categoryId === 0 || $name === '') {
            return;
        }

        $this->index->renameCategory($categoryId, $name);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function clearCategory(array $payload): void
    {
        $rawCategoryId = $payload['category_id'] ?? 0;
        $categoryId = is_numeric($rawCategoryId) ? (int) $rawCategoryId : 0;

        if ($categoryId === 0) {
            return;
        }

        $this->index->clearCategory($categoryId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function stock(array $payload): void
    {
        $id = $this->productId($payload);

        if ($id === '' || ! array_key_exists('available', $payload)) {
            throw new NonRetryableException('stock.changed требует product_id и агрегат available');
        }

        $available = is_numeric($payload['available']) ? (int) $payload['available'] : 0;
        $this->index->updateInStock($id, $available > 0);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function productId(array $payload): string
    {
        $id = $payload['product_id'] ?? $payload['id'] ?? '';

        return is_scalar($id) ? trim((string) $id) : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function body(ConsumerMessage $message): array
    {
        $body = $message->getBody();

        if (! is_array($body) || $body === []) {
            $this->logger->warning('Пустое сообщение catalog.events уходит в DLQ');
            throw new NonRetryableException('Пустое сообщение catalog.events');
        }

        /** @var array<string, mixed> $body */
        return EventEnvelope::flatten($body);
    }
}
