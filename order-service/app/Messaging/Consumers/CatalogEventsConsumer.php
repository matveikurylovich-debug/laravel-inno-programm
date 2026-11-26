<?php

declare(strict_types=1);

namespace App\Messaging\Consumers;

use App\Exceptions\NonRetryableException;
use App\Models\ProductSnapshot;
use Ecommerce\PlatformKit\Messaging\EventEnvelope;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\MessageConsumer;

class CatalogEventsConsumer
{
    public function __invoke(ConsumerMessage $message, MessageConsumer $consumer): void
    {
        $body = $message->getBody();
        if (in_array($body, [null, '', []], true)) {
            Log::warning('Пустое сообщение catalog.events уходит в DLQ');
            throw new NonRetryableException('Пустое сообщение catalog.events');
        }

        if (is_string($body)) {
            $decoded = json_decode($body, true);
            $body = is_array($decoded) ? $decoded : null;
        }

        if (is_object($body)) {
            $body = (array) $body;
        }

        if (! is_array($body) || $body === []) {
            throw new NonRetryableException('Тело события каталога не является объектом');
        }

        /** @var array<string, mixed> $body */
        $body = EventEnvelope::flatten($body);

        $eventType = $message->getHeaders()['event-type'] ?? $body['event'] ?? null;

        Log::info('Получено событие каталога', ['event' => $eventType, 'body' => $body]);

        match ($eventType) {
            'product.created', 'product.updated' => $this->handleProductUpsert($body),
            'product.deleted' => $this->handleProductDeleted($body),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleProductUpsert(array $payload): void
    {
        $productId = $payload['product_id'] ?? $payload['id'] ?? null;

        if (! $productId) {
            throw new NonRetryableException('Событие товара не содержит ID');
        }

        $rawPrice = $payload['price'] ?? 0;
        $price = is_int($rawPrice) || is_float($rawPrice) || is_numeric($rawPrice) ? (float) $rawPrice : 0.0;

        ProductSnapshot::query()->updateOrCreate(
            ['product_id' => $productId],
            [
                'store_id' => $payload['store_id'] ?? null,
                'name' => $payload['name'] ?? 'Без названия',
                'price' => $price,
                'is_active' => (bool) ($payload['is_active'] ?? true),
            ],
        );

        Log::info('Снапшот товара синхронизирован', ['product_id' => $productId]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleProductDeleted(array $payload): void
    {
        $productId = $payload['product_id'] ?? $payload['id'] ?? null;

        if (! $productId) {
            throw new NonRetryableException('Событие удаления товара не содержит ID');
        }

        ProductSnapshot::query()->where('product_id', $productId)->update([
            'is_active' => false,
        ]);

        Log::info('Товар помечен как неактивный', ['product_id' => $productId]);
    }
}
