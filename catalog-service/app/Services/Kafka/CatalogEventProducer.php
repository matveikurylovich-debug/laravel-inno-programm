<?php

namespace App\Services\Kafka;

use Illuminate\Support\Facades\Log;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\Message;
use Throwable;

class CatalogEventProducer
{
    protected string $topic;

    public function __construct()
    {
        $this->topic = (string) config('kafka.topics.catalog_events', 'catalog.events');
    }

    public function productCreated(int $productId, int $storeId): void
    {
        $this->emit('product.created', (string) $productId, [
            'product_id' => $productId,
            'store_id' => $storeId,
        ]);
    }

    public function productUpdated(int $productId, int $storeId): void
    {
        $this->emit('product.updated', (string) $productId, [
            'product_id' => $productId,
            'store_id' => $storeId,
        ]);
    }

    public function productDeleted(int $productId, int $storeId): void
    {
        $this->emit('product.deleted', (string) $productId, [
            'product_id' => $productId,
            'store_id' => $storeId,
        ]);
    }

    public function categoryCreated(int $categoryId, int $storeId): void
    {
        $this->emit('category.created', (string) $categoryId, [
            'category_id' => $categoryId,
            'store_id' => $storeId,
        ]);
    }

    public function categoryUpdated(int $categoryId, int $storeId): void
    {
        $this->emit('category.updated', (string) $categoryId, [
            'category_id' => $categoryId,
            'store_id' => $storeId,
        ]);
    }

    public function categoryDeleted(int $categoryId, int $storeId): void
    {
        $this->emit('category.deleted', (string) $categoryId, [
            'category_id' => $categoryId,
            'store_id' => $storeId,
        ]);
    }

    public function stockChanged(int $productId, int $storeId, int $quantity, int $reservedQuantity): void
    {
        $this->emit('stock.changed', (string) $productId, [
            'product_id' => $productId,
            'store_id' => $storeId,
            'quantity' => $quantity,
            'reserved_quantity' => $reservedQuantity,
            'available' => max(0, $quantity - $reservedQuantity),
        ]);
    }

    protected function emit(string $event, string $key, array $body): void
    {
        try {
            $message = new Message(
                headers: ['service' => 'catalog-service'],
                body: array_merge($body, [
                    'event' => $event,
                    'timestamp' => now()->toISOString(),
                ]),
                key: $key,
            );

            Kafka::publish()
                ->onTopic($this->topic)
                ->withMessage($message)
                ->send();
        } catch (Throwable $e) {
            Log::error("Ошибка отправки события каталога в Kafka: {$e->getMessage()}", [
                'event' => $event,
                'key' => $key,
            ]);
        }
    }
}
