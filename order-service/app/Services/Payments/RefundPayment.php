<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\OrderEventType;
use App\Enums\PaymentStatus;
use App\Messaging\Messages\OrderRefunded;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Outbox\OutboxService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class RefundPayment
{
    public function __construct(
        private OutboxService $outbox,
    ) {}

    /**
     * @param  array<string, mixed>  $refund
     */
    public function handle(array $refund): void
    {
        $intent = $refund['payment_intent'] ?? null;
        $intentId = is_string($intent) ? $intent : null;
        $refundId = $refund['id'] ?? '';
        $refundId = is_string($refundId) ? $refundId : '';
        $status = $refund['status'] ?? '';
        $status = is_string($status) ? $status : '';

        $payment = $refundId !== ''
            ? Payment::query()->where('stripe_refund_id', $refundId)->first()
            : null;
        $payment ??= $intentId !== null
            ? Payment::query()->where('stripe_payment_intent_id', $intentId)->first()
            : null;

        if ($payment === null) {
            Log::warning('Stripe refund: платёж не найден', ['refund_id' => $refundId]);

            return;
        }

        $order = Order::query()->find($payment->order_id);
        if ($order === null) {
            return;
        }

        if ($status === 'succeeded' || ($status === 'pending' && $order->payment_status === PaymentStatus::Refunded)) {
            if ($status === 'succeeded') {
                DB::transaction(function () use ($payment, $order, $refund, $refundId): void {
                    $lockedOrder = Order::query()->lockForUpdate()->find($order->id);
                    if ($lockedOrder === null) {
                        return;
                    }

                    $alreadyRefunded = $lockedOrder->payment_status === PaymentStatus::Refunded;

                    $payment->update(['status' => PaymentStatus::Refunded, 'stripe_refund_id' => $refundId]);
                    $lockedOrder->update(['payment_status' => PaymentStatus::Refunded]);

                    if (! $alreadyRefunded) {
                        $this->outbox->record(
                            OrderEventType::Refunded->value,
                            'Order',
                            (string) $lockedOrder->id,
                            OrderRefunded::toPayload($lockedOrder, $payment, $refund),
                        );
                    }
                });
            }

            return;
        }

        if (in_array($status, ['failed', 'canceled'], true)) {
            DB::transaction(function () use ($payment, $order): void {
                $payment->update(['status' => PaymentStatus::Succeeded]);
                if ($order->payment_status === PaymentStatus::RefundPending) {
                    $order->update(['payment_status' => PaymentStatus::Paid]);
                }
            });
            Log::critical('Возврат Stripe не прошёл', ['refund_id' => $refundId, 'order_id' => $order->id]);

            return;
        }

        DB::transaction(function () use ($payment, $order, $refundId): void {
            $payment->update(['stripe_refund_id' => $refundId]);
            $order->update(['payment_status' => PaymentStatus::RefundPending]);
        });
    }
}
