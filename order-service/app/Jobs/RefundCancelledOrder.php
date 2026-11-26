<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\PaymentGateway;
use App\Enums\OrderEventType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundState;
use App\Messaging\Messages\OrderRefunded;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Outbox\OutboxService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class RefundCancelledOrder implements ShouldQueue
{
    use Queueable;

    public int $tries = 10;

    public function __construct(public string $orderId)
    {
        $this->onQueue('payments');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 300];
    }

    public function failed(Throwable $exception): void
    {
        Log::critical('Возврат по отменённому заказу не выполнен', [
            'order_id' => $this->orderId,
            'exception' => $exception,
        ]);
    }

    public function handle(PaymentGateway $gateway, OutboxService $outbox): void
    {
        $order = Order::query()->findOrFail($this->orderId);
        $payment = Payment::query()->where('order_id', $order->id)->first();
        if ($order->status !== OrderStatus::Cancelled || in_array($order->payment_status, [PaymentStatus::Refunded, PaymentStatus::RefundPending], true)) {
            return;
        }
        if ($payment === null || ! $payment->stripe_payment_intent_id) {
            throw new RuntimeException('Paid order has no payment intent');
        }

        $result = $gateway->refund($payment);
        $orderStatus = $result->state === RefundState::Succeeded ? PaymentStatus::Refunded : PaymentStatus::RefundPending;
        $paymentStatus = $result->state === RefundState::Succeeded ? PaymentStatus::Refunded : PaymentStatus::Succeeded;

        DB::transaction(function () use ($payment, $order, $orderStatus, $paymentStatus, $result, $outbox): void {
            $lockedOrder = Order::query()->lockForUpdate()->find($order->id);
            if ($lockedOrder === null) {
                return;
            }

            $alreadyRefunded = $lockedOrder->payment_status === PaymentStatus::Refunded;

            $payment->update([
                'status' => $paymentStatus,
                'stripe_refund_id' => $result->refundId,
            ]);
            $lockedOrder->update(['payment_status' => $orderStatus]);

            if ($result->state === RefundState::Succeeded && ! $alreadyRefunded) {
                $outbox->record(
                    OrderEventType::Refunded->value,
                    'Order',
                    (string) $lockedOrder->id,
                    OrderRefunded::toPayload(
                        $lockedOrder,
                        $payment,
                        refundedAmount: $result->amount,
                        currency: $result->currency,
                    ),
                );
            }
        });
    }
}
