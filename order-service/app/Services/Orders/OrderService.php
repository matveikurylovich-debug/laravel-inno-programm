<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Data\CreateOrderData;
use App\Models\Order;
use App\Services\Payments\HandlePaymentFailed;
use App\Services\Payments\HandlePaymentSucceeded;
use App\Services\Payments\RefundPayment;

class OrderService
{
    public function __construct(
        private readonly CreateOrder $createOrderAction,
        private readonly CancelOrder $cancelOrderAction,
        private readonly HandlePaymentSucceeded $paymentSucceeded,
        private readonly HandlePaymentFailed $paymentFailed,
        private readonly RefundPayment $refundPayment,
    ) {}

    /**
     * @param  array<int, array{product_id: int|string, quantity: int|string}>  $rawItems
     */
    public function createOrder(int $userId, int $storeId, int $locationId, string $email, array $rawItems): Order
    {
        $lines = [];
        foreach (array_values($rawItems) as $item) {
            $lines[] = [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
            ];
        }

        return $this->createOrderAction->handle(new CreateOrderData(
            userId: $userId,
            email: $email,
            storeId: $storeId,
            locationId: $locationId,
            lines: $lines,
        ));
    }

    public function cancelOrder(Order $order, ?string $reason = null): Order
    {
        return $this->cancelOrderAction->cancel($order, $reason);
    }

    public function republishCreated(Order $order): Order
    {
        return $this->createOrderAction->republish($order);
    }

    /**
     * @param  array<string, mixed>  $paymentIntent
     */
    public function handlePaymentSucceeded(array $paymentIntent): void
    {
        $this->paymentSucceeded->handle($paymentIntent);
    }

    /**
     * @param  array<string, mixed>  $paymentIntent
     */
    public function handlePaymentFailed(array $paymentIntent): void
    {
        $this->paymentFailed->handle($paymentIntent);
    }

    /**
     * @param  array<string, mixed>  $refund
     */
    public function handleRefund(array $refund): void
    {
        $this->refundPayment->handle($refund);
    }
}
