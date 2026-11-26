<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Order;
use App\Models\Payment;
use App\Support\RefundResult;

interface PaymentGateway
{
    public function cancel(Payment $payment): string;

    public function refund(Payment $payment): RefundResult;

    public function createPaymentIntent(Order $order): Payment;

    /**
     * @return array{id: string, type: string, object: array<string, mixed>}
     */
    public function constructEvent(string $payload, string $signature): array;
}
