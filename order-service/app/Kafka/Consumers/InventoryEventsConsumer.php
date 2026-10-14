<?php

declare(strict_types=1);

namespace App\Kafka\Consumers;

use App\Models\Order;
use App\Services\OutboxService;
use App\Services\StripeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\MessageConsumer;
use Throwable;

class InventoryEventsConsumer
{
    public function __construct(
        private readonly OutboxService $outbox,
        private readonly StripeService $stripe,
    ) {}

    public function __invoke(ConsumerMessage $message, MessageConsumer $consumer): void
    {
        $body = $this->body($message);
        $eventType = $message->getHeaders()['event-type'] ?? $body['event'] ?? null;

        Log::info('Получено событие склада', ['event' => $eventType, 'body' => $body]);

        try {
            match ($eventType) {
                'stock.reserved' => $this->handleReserved($body),
                'stock.reservation_failed' => $this->handleFailed($body),
                'stock.committed' => $this->handleCommitted($body),
                default => null,
            };
        } catch (Throwable $e) {
            Log::error("Ошибка обработки события склада {$eventType}: ".$e->getMessage(), [
                'exception' => $e,
                'payload' => $body,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleReserved(array $payload): void
    {
        $order = $this->findOrder($payload);

        if ($order === null || $order->status !== 'created') {
            return;
        }

        DB::transaction(function () use ($order): void {
            $order->update(['status' => 'stock_reserved']);
            $this->stripe->createPaymentIntent($order);
            $order->update(['status' => 'payment_pending']);
        });

        Log::info("Заказ {$order->id} ожидает оплату");
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleFailed(array $payload): void
    {
        $order = $this->findOrder($payload);

        if ($order === null || $order->status !== 'created') {
            return;
        }

        DB::transaction(function () use ($order): void {
            $order->update([
                'status' => 'cancelled',
                'cancel_reason' => 'stock_unavailable',
            ]);

            $this->outbox->record(
                'order.cancelled',
                'Order',
                (string) $order->id,
                array_merge($order->notificationEnvelope([
                    'reason' => 'stock_unavailable',
                ]), [
                    'previous_status' => 'created',
                    'reason' => 'stock_unavailable',
                ]),
            );
        });

        Log::warning("Заказ {$order->id} отменён: stock_unavailable");
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleCommitted(array $payload): void
    {
        $order = $this->findOrder($payload);

        if ($order === null || $order->status !== 'paid') {
            return;
        }

        DB::transaction(function () use ($order): void {
            $order->update(['status' => 'confirmed']);

            $this->outbox->record(
                'order.confirmed',
                'Order',
                (string) $order->id,
                $order->notificationEnvelope(),
            );
        });

        Log::info("Заказ {$order->id} подтверждён после списания остатков");
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function findOrder(array $payload): ?Order
    {
        $orderId = $payload['order_id'] ?? null;

        if (! is_string($orderId) || $orderId === '') {
            Log::warning('Событие склада не содержит order_id', $payload);

            return null;
        }

        $order = Order::query()->find($orderId);

        if ($order === null) {
            Log::warning("Заказ {$orderId} не найден");
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

        return is_array($body) ? $body : [];
    }
}
