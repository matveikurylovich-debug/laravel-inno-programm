<?php

namespace App\Kafka\Consumers;

use App\Kafka\Consumers\OrderEvents\CommitStockOnOrderPaid;
use App\Kafka\Consumers\OrderEvents\HandlesOrderEvent;
use App\Kafka\Consumers\OrderEvents\ReleaseStockReservation;
use App\Kafka\Consumers\OrderEvents\ReserveStockOnOrderCreated;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\MessageConsumer;

class OrderEventsHandler
{
    /** @var array<string, HandlesOrderEvent> */
    private array $handlers;

    public function __construct(
        ReserveStockOnOrderCreated $reserveStock,
        CommitStockOnOrderPaid $commitStock,
        ReleaseStockReservation $releaseStock,
    ) {
        $this->handlers = [
            'order.created' => $reserveStock,
            'order.paid' => $commitStock,
            'order.cancelled' => $releaseStock,
            'stock.release_requested' => $releaseStock,
        ];
    }

    public function __invoke(ConsumerMessage $message, ?MessageConsumer $consumer = null): void
    {
        $payload = $this->payload($message);

        if ($payload === null) {
            return;
        }

        $eventType = $payload['event'] ?? null;
        $orderId = $payload['order_id'] ?? null;

        if (! is_string($eventType) || $eventType === '' || $orderId === null || $orderId === '') {
            Log::warning('Получено некорректное сообщение из Kafka', ['message' => $payload]);

            return;
        }

        Log::info("Обработка события Kafka: {$eventType} для заказа {$orderId}");

        $handler = $this->handlers[$eventType] ?? null;

        if ($handler === null) {
            Log::info("Игнорирование неизвестного типа события: {$eventType}");

            return;
        }

        $handler->handle($payload);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function payload(ConsumerMessage $message): ?array
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

            return null;
        }

        return $payload;
    }
}
