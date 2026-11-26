<?php

declare(strict_types=1);

namespace App\Messaging\Consumers;

use App\Exceptions\NonRetryableException;
use App\Messaging\Handlers\OrderEvents\CommitStockOnOrderPaid;
use App\Messaging\Handlers\OrderEvents\OrderEventHandler;
use App\Messaging\Handlers\OrderEvents\ReleaseStockOnOrderCancelled;
use App\Messaging\Handlers\OrderEvents\ReserveStockOnOrderCreated;
use Ecommerce\PlatformKit\Messaging\EventEnvelope;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\Handler;
use Junges\Kafka\Contracts\MessageConsumer;

class OrderEventsConsumer implements Handler
{
    /** @var array<string, OrderEventHandler> */
    private array $handlers;

    public function __construct(
        ReserveStockOnOrderCreated $reserveStock,
        CommitStockOnOrderPaid $commitStock,
        ReleaseStockOnOrderCancelled $releaseStock,
    ) {
        $this->handlers = [
            'order.created' => $reserveStock,
            'order.paid' => $commitStock,
            'stock.commit_requested' => $commitStock,
            'order.cancelled' => $releaseStock,
            'stock.release_requested' => $releaseStock,
        ];
    }

    public function __invoke(ConsumerMessage $message, ?MessageConsumer $consumer = null): void
    {
        $payload = $this->payload($message);
        $eventType = $payload['event'] ?? null;
        $orderId = $payload['order_id'] ?? null;

        if (! is_string($eventType) || $eventType === '' || $orderId === null || $orderId === '') {
            throw new NonRetryableException('Сообщение заказа не содержит event или order_id');
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
     * @return array<string, mixed>
     */
    private function payload(ConsumerMessage $message): array
    {
        $payload = $message->getBody();

        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }

        if (is_object($payload)) {
            $payload = (array) $payload;
        }

        if (! is_array($payload) || $payload === []) {
            throw new NonRetryableException('Тело сообщения заказа не является объектом');
        }

        return EventEnvelope::flatten($payload);
    }
}
