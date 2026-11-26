<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\OrderEventType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\RefundCancelledOrder;
use App\Messaging\Messages\OrderPaid;
use App\Messaging\Messages\StockCommitRequested;
use App\Models\Order;
use App\Services\Orders\OrderLock;
use App\Services\Outbox\OutboxService;
use Illuminate\Support\Facades\Log;

final readonly class HandlePaymentSucceeded
{
    public function __construct(
        private OutboxService $outbox,
        private OrderLock $lock,
    ) {}

    /**
     * Оплата прошла: заказ становится paid и просит склад списать резерв.
     * Подтверждение приходит только после stock.committed.
     *
     * @param  array<string, mixed>  $paymentIntent
     */
    public function handle(array $paymentIntent): void
    {
        $orderId = StripePaymentSync::orderId($paymentIntent);

        if ($orderId === null) {
            return;
        }

        $this->lock->attempt($orderId, function (?Order $order) use ($orderId, $paymentIntent): void {
            if (! $order instanceof Order) {
                Log::warning('Stripe succeeded: заказ не найден', ['order_id' => $orderId]);

                return;
            }

            if (in_array($order->status, [OrderStatus::Paid, OrderStatus::Confirmed], true)) {
                return;
            }

            if ($order->payment_status === PaymentStatus::Refunded) {
                return;
            }

            StripePaymentSync::apply($order, $paymentIntent, PaymentStatus::Succeeded);

            if ($order->status === OrderStatus::Cancelled) {
                $order->update(['payment_status' => PaymentStatus::Paid]);
                dispatch(new RefundCancelledOrder((string) $order->id))->onConnection('database');

                return;
            }

            if ($order->status !== OrderStatus::PaymentPending) {
                return;
            }

            $order->transitionTo(OrderStatus::Paid);
            $order->update(['payment_status' => PaymentStatus::Paid]);

            $this->outbox->record(
                OrderEventType::Paid->value,
                'Order',
                (string) $order->id,
                OrderPaid::toPayload($order),
            );

            $this->outbox->record(
                OrderEventType::StockCommitRequested->value,
                'Order',
                (string) $order->id,
                StockCommitRequested::for($order),
            );
        });
    }
}
