<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use InvalidArgumentException;

class OrderService
{
    public function __construct(
        private readonly OutboxService $outbox,
    ) {}

    /**
     * @param  array<int, array{product_id: int|string, quantity: int|string}>  $rawItems
     */
    public function createOrder(int $userId, int $storeId, int $locationId, string $email, array $rawItems): Order
    {
        $productIds = array_values(array_unique(array_map(
            static fn (array $item): int => (int) $item['product_id'],
            $rawItems,
        )));

        $snapshots = ProductSnapshot::query()
            ->where('store_id', $storeId)
            ->where('is_active', true)
            ->whereIn('product_id', $productIds)
            ->get()
            ->keyBy('product_id');

        // можно сделать указание конкретных товаров которые не досутпны
        if ($snapshots->count() !== count($productIds)) {
            throw new InvalidArgumentException('Один или несколько товаров недоступны в этом магазине');
        }

        return DB::transaction(function () use ($userId, $storeId, $locationId, $email, $rawItems, $snapshots): Order {
            $lines = [];
            $total = 0.0;

            foreach ($rawItems as $item) {
                /** @var ProductSnapshot $snapshot */
                $snapshot = $snapshots->get((int) $item['product_id']);
                $quantity = (int) $item['quantity'];
                $unitPrice = round((float) $snapshot->price, 2);
                $subtotal = round($unitPrice * $quantity, 2);
                $total += $subtotal;

                $lines[] = [
                    'product_id' => (int) $snapshot->product_id,
                    'product_name' => $snapshot->name,
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'subtotal' => $subtotal,
                ];
            }

            $order = Order::query()->create([
                'user_id' => $userId,
                'email' => $email,
                'store_id' => $storeId,
                'location_id' => $locationId,
                'status' => 'created',
                'payment_status' => 'pending',
                'total_amount' => round($total, 2),
                'currency' => 'usd',
            ]);

            $order->items()->createMany($lines);

            $this->outbox->record(
                'order.created',
                'Order',
                (string) $order->id,
                array_merge($order->notificationEnvelope(), [
                    'items' => array_map(static fn (array $line): array => [
                        'product_id' => $line['product_id'],
                        'quantity' => $line['quantity'],
                        'price' => $line['unit_price'],
                    ], $lines),
                ]),
            );

            return $order->load('items');
        });
    }

    public function cancelOrder(Order $order, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($order, $reason): Order {
            $previousStatus = $order->status;

            $order->update([
                'status' => 'cancelled',
                'cancel_reason' => $reason ?? 'Cancelled by user',
            ]);

            if (in_array($previousStatus, ['stock_reserved', 'payment_pending'], true)) {
                $this->outbox->record(
                    'stock.release_requested',
                    'Order',
                    (string) $order->id,
                    [
                        'order_id' => $order->id,
                        'store_id' => $order->store_id,
                    ],
                );
            }

            $this->outbox->record(
                'order.cancelled',
                'Order',
                (string) $order->id,
                array_merge($order->notificationEnvelope([
                    'reason' => $order->cancel_reason,
                ]), [
                    'previous_status' => $previousStatus,
                    'reason' => $order->cancel_reason,
                ]),
            );

            return $order->fresh('items');
        });
    }

    public function deleteOrder(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            if (in_array($order->status, ['stock_reserved', 'payment_pending'], true)) {
                $this->outbox->record(
                    'stock.release_requested',
                    'Order',
                    (string) $order->id,
                    [
                        'order_id' => $order->id,
                        'store_id' => $order->store_id,
                    ],
                );
            }

            $order->delete();
        });

        Redis::del("order:saga:{$order->id}");
    }

    /**
     * Сценарий A: payment_intent.succeeded — подтверждение оплаты и фиксация списания.
     *
     * @param  array<string, mixed>  $paymentIntent
     */
    public function handlePaymentSucceeded(array $paymentIntent): void
    {
        $orderId = $this->orderIdFromPaymentIntent($paymentIntent);

        if ($orderId === null) {
            return;
        }

        DB::transaction(function () use ($orderId, $paymentIntent): void {
            /** @var Order|null $order */
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();

            if ($order === null) {
                Log::warning("Stripe succeeded: заказ {$orderId} не найден");

                return;
            }

            if (in_array($order->status, ['paid', 'confirmed'], true)) {
                return;
            }

            $this->updatePaymentFromIntent($order, $paymentIntent, 'succeeded');

            $order->update([
                'status' => 'paid',
                'payment_status' => 'paid',
            ]);

            $order->update([
                'status' => 'confirmed',
            ]);

            $this->outbox->record(
                'order.paid',
                'Order',
                (string) $order->id,
                $order->notificationEnvelope(),
            );

            $this->outbox->record(
                'order.confirmed',
                'Order',
                (string) $order->id,
                $order->notificationEnvelope(),
            );
        });
    }

    /**
     * Сценарий B: payment_intent.payment_failed — компенсация саги (снятие брони).
     *
     * @param  array<string, mixed>  $paymentIntent
     */
    public function handlePaymentFailed(array $paymentIntent): void
    {
        $orderId = $this->orderIdFromPaymentIntent($paymentIntent);

        if ($orderId === null) {
            return;
        }

        DB::transaction(function () use ($orderId, $paymentIntent): void {
            /** @var Order|null $order */
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();

            if ($order === null) {
                Log::warning("Stripe payment_failed: заказ {$orderId} не найден");

                return;
            }

            if (in_array($order->status, ['payment_failed', 'cancelled', 'paid', 'confirmed'], true)) {
                return;
            }

            $this->updatePaymentFromIntent($order, $paymentIntent, 'failed');

            $order->update([
                'status' => 'payment_failed',
                'payment_status' => 'failed',
            ]);

            $this->outbox->record(
                'stock.release_requested',
                'Order',
                (string) $order->id,
                [
                    'order_id' => $order->id,
                    'store_id' => $order->store_id,
                ],
            );

            $this->outbox->record(
                'order.payment_failed',
                'Order',
                (string) $order->id,
                [
                    'order_id' => $order->id,
                    'store_id' => $order->store_id,
                    'user_id' => $order->user_id,
                    'total_amount' => $order->total_amount,
                ],
            );
        });
    }

    /**
     * @param  array<string, mixed>  $paymentIntent
     */
    private function orderIdFromPaymentIntent(array $paymentIntent): ?string
    {
        $orderId = $paymentIntent['metadata']['order_id'] ?? null;

        if (! is_string($orderId) || $orderId === '') {
            Log::warning('Stripe webhook: отсутствует metadata.order_id', [
                'payment_intent_id' => $paymentIntent['id'] ?? null,
            ]);

            return null;
        }

        return $orderId;
    }

    /**
     * @param  array<string, mixed>  $paymentIntent
     */
    private function updatePaymentFromIntent(Order $order, array $paymentIntent, string $status): void
    {
        $intentId = isset($paymentIntent['id']) && is_string($paymentIntent['id'])
            ? $paymentIntent['id']
            : null;

        $payment = null;

        if ($intentId !== null) {
            $payment = Payment::query()
                ->where('stripe_payment_intent_id', $intentId)
                ->first();
        }

        $payment ??= $order->payment()->first();

        if ($payment === null) {
            Log::warning("Stripe webhook: платёж для заказа {$order->id} не найден", [
                'payment_intent_id' => $intentId,
                'status' => $status,
            ]);

            return;
        }

        $payment->update([
            'status' => $status,
            'raw_response' => $paymentIntent,
        ]);
    }
}
