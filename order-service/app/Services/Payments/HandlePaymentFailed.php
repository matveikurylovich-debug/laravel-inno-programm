<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\OrderEventType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Messaging\Messages\OrderPaymentFailed;
use App\Models\Order;
use App\Services\Orders\OrderLock;
use App\Services\Outbox\OutboxService;
use Illuminate\Support\Facades\Log;

final readonly class HandlePaymentFailed
{
    public function __construct(
        private OutboxService $outbox,
        private OrderLock $lock,
    ) {}

    /**
     * Отказ карты не терминальный: заказ остаётся payment_pending, резерв сохраняется.
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
                Log::warning('Stripe payment_failed: заказ не найден', ['order_id' => $orderId]);

                return;
            }

            if (in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Paid, OrderStatus::Confirmed], true)) {
                return;
            }

            if ($order->status !== OrderStatus::PaymentPending) {
                return;
            }

            StripePaymentSync::apply($order, $paymentIntent, PaymentStatus::Failed);
            $order->update(['payment_status' => PaymentStatus::Failed]);

            $this->outbox->record(
                OrderEventType::PaymentFailed->value,
                'Order',
                (string) $order->id,
                OrderPaymentFailed::for($order),
            );
        });
    }
}
