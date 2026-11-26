<?php

declare(strict_types=1);

namespace App\Messaging\Producers;

use App\Enums\InventoryEvent;
use App\Models\CatalogOutbox;
use Illuminate\Support\Facades\Log;

class InventoryEventsProducer
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
            'event' => InventoryEvent::Reserved->value,
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
            'event' => InventoryEvent::ReservationFailed->value,
            'order_id' => $orderId,
            'store_id' => $storeId,
            'reason' => $reason,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Уведомление: бронь списана после оплаты.
     */
    public function emitCommitted(string $orderId, int $storeId): void
    {
        $this->send($orderId, [
            'event' => InventoryEvent::Committed->value,
            'order_id' => $orderId,
            'store_id' => $storeId,
            'timestamp' => now()->toISOString(),
        ]);
    }

    public function emitCommitFailed(string $orderId, int $storeId, string $reason): void
    {
        $this->send($orderId, [
            'event' => InventoryEvent::CommitFailed->value,
            'order_id' => $orderId,
            'store_id' => $storeId,
            'reason' => $reason,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function send(string $key, array $payload): void
    {
        CatalogOutbox::query()->create([
            'topic' => $this->topic,
            'message_key' => $key,
            'event' => $payload['event'],
            'payload' => $payload,
            'created_at' => now(),
            'attempts' => 0,
        ]);

        Log::info("Событие склада [{$payload['event']}] записано в outbox для заказа: {$key}");
    }
}
