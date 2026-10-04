<?php

namespace App\Services\Kafka;

use Illuminate\Support\Facades\Log;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\Message;
use Throwable;

class InventoryEventProducer
{
    protected string $topic;

    public function __construct()
    {
        $this->topic = (string) config('kafka.topics.inventory_events', 'inventory.events');
    }

    /**
     * Уведомление: товары успешно зарезервированы под заказ.
     */
    public function emitReserved(string $orderId, int $storeId): void
    {
        $this->send($orderId, [
            'event' => 'stock.reserved',
            'order_id' => $orderId,
            'store_id' => $storeId,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Уведомление: товаров на складе недостаточно.
     */
    public function emitFailed(string $orderId, int $storeId, string $reason): void
    {
        $this->send($orderId, [
            'event' => 'stock.reservation_failed',
            'order_id' => $orderId,
            'store_id' => $storeId,
            'reason' => $reason,
            'timestamp' => now()->toISOString(),
        ]);
    }

    protected function send(string $key, array $payload): void
    {
        try {
            $message = new Message(
                headers: ['service' => 'catalog-service'],
                body: $payload,
                key: $key,
            );

            Kafka::publish()
                ->onTopic($this->topic)
                ->withMessage($message)
                ->send();

            Log::info("Kafka событие [{$payload['event']}] отправлено для заказа: {$key}");
        } catch (Throwable $e) {
            Log::error("Ошибка отправки события в Kafka: {$e->getMessage()}", [
                'payload' => $payload,
            ]);
        }
    }
}
