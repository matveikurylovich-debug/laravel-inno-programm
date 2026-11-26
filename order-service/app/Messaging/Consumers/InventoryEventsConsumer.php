<?php

declare(strict_types=1);

namespace App\Messaging\Consumers;

use App\Contracts\PaymentGateway;
use App\Enums\OrderEventType;
use App\Enums\OrderStatus;
use App\Exceptions\NonRetryableException;
use App\Messaging\Messages\OrderCancelled;
use App\Messaging\Messages\OrderConfirmed;
use App\Models\Order;
use App\Services\Orders\CancelOrder;
use App\Services\Orders\OrderLock;
use App\Services\Outbox\OutboxService;
use Ecommerce\PlatformKit\Messaging\EventEnvelope;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\MessageConsumer;
use RuntimeException;

class InventoryEventsConsumer
{
    public function __construct(
        private readonly OutboxService $outbox,
        private readonly PaymentGateway $stripe,
        private readonly CancelOrder $orders,
        private readonly OrderLock $lock,
    ) {}

    public function __invoke(ConsumerMessage $message, MessageConsumer $consumer): void
    {
        $body = $this->body($message);
        $eventType = $message->getHeaders()['event-type'] ?? $body['event'] ?? null;

        Log::info('Получено событие склада', ['event' => $eventType, 'body' => $body]);

        match ($eventType) {
            'stock.reserved' => $this->handleReserved($body),
            'stock.reservation_failed' => $this->handleFailed($body),
            'stock.committed' => $this->handleCommitted($body),
            'stock.commit_failed' => $this->handleCommitFailed($body),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleReserved(array $payload): void
    {
        $order = $this->findOrder($payload);

        $phase = $this->lock->attempt((string) $order->id, function (?Order $locked): string {
            if (! $locked instanceof Order) {
                return 'stop';
            }

            if ($locked->status === OrderStatus::Created) {
                $locked->transitionTo(OrderStatus::StockReserved);

                return 'pay';
            }

            if ($locked->status !== OrderStatus::StockReserved) {
                return 'stop';
            }

            return $locked->payment === null ? 'pay' : 'finish';
        });

        if ($phase === 'stop') {
            return;
        }

        if ($phase === 'pay') {
            $fresh = $order->fresh();
            if ($fresh !== null && $fresh->payment === null) {
                $this->stripe->createPaymentIntent($fresh);
            }
        }

        $this->lock->attempt((string) $order->id, function (?Order $locked): void {
            if ($locked instanceof Order && $locked->status === OrderStatus::StockReserved) {
                $locked->transitionTo(OrderStatus::PaymentPending);
            }
        });

        Log::info('Заказ ожидает оплату', ['order_id' => $order->id]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleFailed(array $payload): void
    {
        $order = $this->findOrder($payload);

        $this->lock->attempt((string) $order->id, function (?Order $locked): void {
            if (! $locked instanceof Order || $locked->status !== OrderStatus::Created) {
                return;
            }

            $locked->transitionTo(OrderStatus::Cancelled);
            $locked->update(['cancel_reason' => 'stock_unavailable']);

            $this->outbox->record(
                OrderEventType::Cancelled->value,
                'Order',
                (string) $locked->id,
                OrderCancelled::toPayload($locked, OrderStatus::Created->value),
            );
        });

        Log::warning('Заказ отменён: нет товара на складе', [
            'order_id' => $order->id,
            'reason' => 'stock_unavailable',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleCommitted(array $payload): void
    {
        $order = $this->findOrder($payload);

        $this->lock->attempt((string) $order->id, function (?Order $locked): void {
            if (! $locked instanceof Order || $locked->status !== OrderStatus::Paid) {
                return;
            }

            $locked->transitionTo(OrderStatus::Confirmed);

            $this->outbox->record(
                OrderEventType::Confirmed->value,
                'Order',
                (string) $locked->id,
                OrderConfirmed::toPayload($locked),
            );
        });

        Log::info('Заказ подтверждён после списания остатков', ['order_id' => $order->id]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleCommitFailed(array $payload): void
    {
        $order = $this->findOrder($payload);

        if ($order->status !== OrderStatus::Paid) {
            return;
        }

        $this->orders->cancel($order, 'stock_commit_failed');
        Log::warning('Заказ отменён: склад не списал резерв', [
            'order_id' => $order->id,
            'reason' => 'stock_commit_failed',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function findOrder(array $payload): Order
    {
        $orderId = $payload['order_id'] ?? null;

        if (! is_string($orderId) || $orderId === '') {
            throw new NonRetryableException('Событие склада не содержит order_id');
        }

        $order = Order::query()->find($orderId);

        if ($order === null) {
            throw new RuntimeException("Заказ {$orderId} не найден");
        }

        return $order;
    }

    /**
     * @return array<string, mixed>
     */
    private function body(ConsumerMessage $message): array
    {
        $body = $message->getBody();

        if (is_string($body)) {
            $body = json_decode($body, true);
        }

        if (is_object($body)) {
            $body = (array) $body;
        }

        if (! is_array($body) || $body === []) {
            throw new NonRetryableException('Тело события склада не является объектом');
        }

        /** @var array<string, mixed> $body */
        return EventEnvelope::flatten($body);
    }
}
