<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Order;
use App\Models\Payment;
use App\Services\StripeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class RefundCancelledOrder implements ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public function __construct(public string $orderId) {}

    public function backoff(): array
    {
        return [10, 30, 60, 300];
    }

    public function handle(StripeService $stripe): void
    {
        $order = Order::withTrashed()->findOrFail($this->orderId);
        $payment = Payment::query()->where('order_id', $order->id)->first();
        if ($order->status !== 'cancelled' || $order->payment_status === 'refunded') {
            return;
        }
        if ($payment === null || ! $payment->stripe_payment_intent_id) {
            throw new \RuntimeException('Paid order has no payment intent');
        }
        $stripe->refund($payment);
        DB::transaction(function () use ($payment, $order): void {
            $payment->update(['status' => 'refunded']);
            $order->update(['payment_status' => 'refunded']);
        });
    }
}
