<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Contracts\PaymentGateway;
use App\Enums\OrderEventType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\OrderNotCancellable;
use App\Jobs\RefundCancelledOrder;
use App\Messaging\Messages\OrderCancelled;
use App\Messaging\Messages\StockReleaseRequested;
use App\Models\Order;
use App\Services\Outbox\OutboxService;
use InvalidArgumentException;

final readonly class CancelOrder
{
    public function __construct(
        private OutboxService $outbox,
        private OrderLock $lock,
        private PaymentGateway $payments,
    ) {}

    public function cancel(Order $order, ?string $reason = null): Order
    {
        $order->loadMissing('payment');
        if ($order->status === OrderStatus::PaymentPending && $order->payment !== null) {
            $intentStatus = $this->payments->cancel($order->payment);
            if ($intentStatus === 'succeeded') {
                $this->lock->run((string) $order->id, function (Order $locked): void {
                    if ($locked->status === OrderStatus::PaymentPending) {
                        $locked->transitionTo(OrderStatus::Paid);
                    }
                    $locked->update(['payment_status' => PaymentStatus::Paid]);
                });
                $order->refresh();
            }
        }

        return $this->lock->run((string) $order->id, function (Order $order) use ($reason): Order {
            if ($order->status === OrderStatus::Cancelled) {
                return $order->load('items');
            }

            $previousStatus = $order->status->value;

            try {
                $order->transitionTo(OrderStatus::Cancelled);
            } catch (InvalidArgumentException $exception) {
                throw new OrderNotCancellable($exception->getMessage(), $exception->getCode(), previous: $exception);
            }

            $order->update([
                'cancel_reason' => $reason ?? 'Cancelled by user',
            ]);

            $this->outbox->record(
                OrderEventType::StockReleaseRequested->value,
                'Order',
                (string) $order->id,
                StockReleaseRequested::for($order),
            );

            if ($order->payment_status === PaymentStatus::Paid) {
                dispatch(new RefundCancelledOrder((string) $order->id))->onConnection('database');
            }

            $this->outbox->record(
                OrderEventType::Cancelled->value,
                'Order',
                (string) $order->id,
                OrderCancelled::toPayload($order, $previousStatus),
            );

            return $order->fresh('items') ?? $order;
        });
    }
}
