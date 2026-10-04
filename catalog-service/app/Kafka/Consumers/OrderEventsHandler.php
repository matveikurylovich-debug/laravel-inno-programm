<?php

namespace App\Kafka\Consumers;

use App\Services\Kafka\InventoryEventProducer;
use App\Services\StockReservationService;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\MessageConsumer;

class OrderEventsHandler
{
    public function __construct(
        protected StockReservationService $reservationService,
        protected InventoryEventProducer $producer,
    ) {}

    public function __invoke(ConsumerMessage $message, ?MessageConsumer $consumer = null): void
    {
        $payload = $message->getBody();

        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }

        if (is_object($payload)) {
            $payload = (array) $payload;
        }

        if (! is_array($payload)) {
            Log::warning('Получено некорректное сообщение из Kafka', ['message' => $payload]);

            return;
        }

        $eventType = $payload['event'] ?? null;
        $orderId = $payload['order_id'] ?? null;
        $storeId = (int) ($payload['store_id'] ?? 0);

        if (! $orderId || ! $eventType) {
            Log::warning('Получено некорректное сообщение из Kafka', ['message' => $payload]);

            return;
        }

        Log::info("Обработка события Kafka: {$eventType} для заказа {$orderId}");

        switch ($eventType) {
            case 'order.created':
                $items = $payload['items'] ?? [];
                if (is_object($items)) {
                    $items = (array) $items;
                }

                $success = $this->reservationService->reserve((string) $orderId, $storeId, $items);

                if ($success) {
                    $this->producer->emitReserved((string) $orderId, $storeId);
                } else {
                    $this->producer->emitFailed((string) $orderId, $storeId, 'Insufficient stock');
                }
                break;

            case 'order.paid':
                $this->reservationService->commit((string) $orderId);
                break;

            case 'order.cancelled':
            case 'stock.release_requested':
                $this->reservationService->release((string) $orderId);
                break;

            default:
                Log::info("Игнорирование неизвестного типа события: {$eventType}");
                break;
        }
    }
}
