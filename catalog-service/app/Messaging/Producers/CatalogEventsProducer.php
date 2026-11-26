<?php

declare(strict_types=1);

namespace App\Messaging\Producers;

use App\Enums\InventoryEvent;
use App\Models\CatalogOutbox;

class CatalogEventsProducer
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
            'category_path' => [],
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
            'category_path' => [],
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

    /**
     * @param  list<array{location_id: int|null, quantity: int, reserved_quantity: int, available: int}>  $locations
     */
    public function stockChanged(int $productId, int $storeId, int $available, array $locations): void
    {
        $this->emit(InventoryEvent::Changed->value, (string) $productId, [
            'product_id' => $productId,
            'store_id' => $storeId,
            'available' => $available,
            'is_in_stock' => $available > 0,
            'locations' => $locations,
        ]);
    }

    /**
     * @param  array<string, mixed>  $body
     */
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
