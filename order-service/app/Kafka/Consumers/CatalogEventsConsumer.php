<?php

declare(strict_types=1);

namespace App\Kafka\Consumers;

use App\Models\ProductSnapshot;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\MessageConsumer;
use Throwable;

class CatalogEventsConsumer
{
    public function __invoke(ConsumerMessage $message, MessageConsumer $consumer): void
    {
        $body = $message->getBody();
        $eventType = $message->getHeaders()['event-type'] ?? $body['event'] ?? null;

        Log::info("Получено событие каталога: {$eventType}", ['body' => $body]);

        try {
            match ($eventType) {
                'product.created', 'product.updated' => $this->handleProductUpsert($body),
                'product.deleted' => $this->handleProductDeleted($body),
                default => null, // Игнорируем остальные события (например, категории)
            };
        } catch (Throwable $e) {
            Log::error("Ошибка обработки события каталога {$eventType}: ".$e->getMessage(), [
                'exception' => $e,
                'payload' => $body,
            ]);
        }
    }

    private function handleProductUpsert(array $payload): void
    {
        $productId = $payload['product_id'] ?? $payload['id'] ?? null;

        if (! $productId) {
            Log::warning('Событие товара не содержит ID', $payload);

            return;
        }

        ProductSnapshot::updateOrCreate(
            ['product_id' => $productId],
            [
                'store_id' => $payload['store_id'] ?? null,
                'name' => $payload['name'] ?? 'Без названия',
                'price' => (float) ($payload['price'] ?? 0),
                'is_active' => (bool) ($payload['is_active'] ?? true),
            ]
        );

        Log::info("Снапшот товара синхронизирован: {$productId}");
    }

    private function handleProductDeleted(array $payload): void
    {
        $productId = $payload['product_id'] ?? $payload['id'] ?? null;

        if (! $productId) {
            return;
        }

        ProductSnapshot::where('product_id', $productId)->update([
            'is_active' => false,
        ]);

        Log::info("Товар помечен как неактивный: {$productId}");
    }
}
