<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeService
{
    private StripeClient $client;

    public function __construct()
    {
        $secretKey = (string) config('services.stripe.secret');

        if ($secretKey === '') {
            throw new RuntimeException('Stripe secret key is not configured');
        }

        $this->client = new StripeClient($secretKey);
    }

    /**
     * Создает PaymentIntent в Stripe и сохраняет запись Payment в БД.
     */
    public function createPaymentIntent(Order $order): Payment
    {
        // Stripe принимает сумму в минимальных единицах (центах)
        $amountInCents = (int) round($order->total_amount * 100);
        $currency = strtolower($order->currency ?? config('services.stripe.currency', 'usd'));

        try {
            $intent = $this->client->paymentIntents->create([
                'amount' => $amountInCents,
                'currency' => $currency,
                'metadata' => [
                    'order_id' => (string) $order->id,
                    'user_id' => (string) $order->user_id,
                ],
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);

            return Payment::create([
                'order_id' => $order->id,
                'stripe_payment_intent_id' => $intent->id,
                'stripe_client_secret' => $intent->client_secret,
                'amount' => $order->total_amount,
                'currency' => strtoupper($currency),
                'status' => 'pending',
                'raw_response' => $intent->toArray(),
            ]);

        } catch (ApiErrorException $e) {
            Log::error("Ошибка создания Stripe PaymentIntent для заказа {$order->id}: {$e->getMessage()}", [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException("Не удалось инициализировать оплату через Stripe: {$e->getMessage()}", 0, $e);
        }
    }
}
