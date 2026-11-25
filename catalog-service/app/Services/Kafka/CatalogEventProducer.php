<?php

namespace App\Services\Kafka;

use App\Models\CatalogOutbox;

class CatalogEventProducer
{
    protected string $topic;

    public function __construct()
    {
        $this->topic = (string) config('kafka.topics.catalog_events', 'catalog.events');
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function productCreated(int $productId, int $storeId, string $name, string $price, bool $isActive, array $extra = []): void
    {
        $this->emit('product.created', (string) $productId, array_merge([
            'product_id' => $productId,
            'store_id' => $storeId,
            'name' => $name,
            'price' => $price,
            'is_active' => $isActive,
        ], $extra));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function productUpdated(int $productId, int $storeId, string $name, string $price, bool $isActive, array $extra = []): void
    {
        $this->emit('product.updated', (string) $productId, array_merge([
            'product_id' => $productId,
            'store_id' => $storeId,
            'name' => $name,
            'price' => $price,
            'is_active' => $isActive,
        ], $extra));
    }

    public function productDeleted(int $productId, int $storeId): void
    {
        $this->emit('product.deleted', (string) $productId, [
            'product_id' => $productId,
            'store_id' => $storeId,
        ]);
    }

    public function categoryCreated(int $categoryId, int $storeId, string $name = ''): void
    {
        $this->emit('category.created', (string) $categoryId, $this->categoryBody($categoryId, $storeId, $name));
    }

    public function categoryUpdated(int $categoryId, int $storeId, string $name = ''): void
    {
        $this->emit('category.updated', (string) $categoryId, $this->categoryBody($categoryId, $storeId, $name));
    }

    public function categoryDeleted(int $categoryId, int $storeId, string $name = ''): void
    {
        $this->emit('category.deleted', (string) $categoryId, $this->categoryBody($categoryId, $storeId, $name));
    }

    /**
     * @return array<string, int|string>
     */
    private function categoryBody(int $categoryId, int $storeId, string $name): array
    {
        $body = [
            'category_id' => $categoryId,
            'store_id' => $storeId,
        ];

        if ($name !== '') {
            $body['category_name'] = $name;
            $body['name'] = $name;
        }

        return $body;
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
        CatalogOutbox::query()->create([
            'topic' => $this->topic,
            'message_key' => $key,
            'event' => $event,
            'payload' => array_merge($body, [
                'event' => $event,
                'timestamp' => now()->toISOString(),
            ]),
            'created_at' => now(),
            'attempts' => 0,
        ]);
    }
}
