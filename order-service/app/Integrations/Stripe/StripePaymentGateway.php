<?php

declare(strict_types=1);

namespace App\Integrations\Stripe;

use App\Contracts\PaymentGateway;
use App\Enums\RefundState;
use App\Exceptions\InvalidWebhookPayload;
use App\Exceptions\InvalidWebhookSignature;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Money;
use App\Support\RefundResult;
use Closure;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Refund;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

class StripePaymentGateway implements PaymentGateway
{
    /**
     * @param  Closure(): StripeClient  $clientResolver
     */
    public function __construct(
        private readonly Closure $clientResolver,
    ) {}

    public function cancel(Payment $payment): string
    {
        if ($payment->stripe_payment_intent_id === null || $payment->stripe_payment_intent_id === '') {
            return 'canceled';
        }

        $intent = $this->client()->paymentIntents->retrieve($payment->stripe_payment_intent_id);
        if (in_array($intent->status, ['succeeded', 'canceled'], true)) {
            return (string) $intent->status;
        }

        $canceled = $this->client()->paymentIntents->cancel($payment->stripe_payment_intent_id, [], [
            'idempotency_key' => 'cancel-payment-intent-'.$payment->order_id,
        ]);

        return (string) $canceled->status;
    }

    public function refund(Payment $payment): RefundResult
    {
        $intentId = $payment->stripe_payment_intent_id;
        if ($intentId === null || $intentId === '') {
            throw new RuntimeException('Payment has no Stripe payment intent ID');
        }

        $existing = $this->client()->refunds->all([
            'payment_intent' => $intentId,
            'limit' => 10,
        ]);

        foreach ($existing->data as $refund) {
            if (in_array($refund->status, ['succeeded', 'pending', 'requires_action'], true)) {
                return $this->refundResult($refund);
            }
        }

        return $this->refundResult($this->client()->refunds->create(
            ['payment_intent' => $intentId],
            ['idempotency_key' => 'cancel-order-'.$payment->order_id],
        ));
    }

    /**
     * @return array{id: string, type: string, object: array<string, mixed>}
     */
    public function constructEvent(string $payload, string $signature): array
    {
        $rawSecret = config('services.stripe.webhook_secret');
        $secret = is_string($rawSecret) ? $rawSecret : '';

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (UnexpectedValueException $exception) {
            throw new InvalidWebhookPayload($exception->getMessage(), $exception->getCode(), previous: $exception);
        } catch (SignatureVerificationException $exception) {
            throw new InvalidWebhookSignature($exception->getMessage(), $exception->getCode(), previous: $exception);
        }

        $decoded = $event->toArray();
        $data = $decoded['data'] ?? null;
        $object = is_array($data) ? ($data['object'] ?? null) : null;
        $id = $decoded['id'] ?? null;
        $type = $decoded['type'] ?? null;

        if (! is_string($id) || $id === '' || ! is_string($type) || $type === '' || ! is_array($object)) {
            throw new InvalidWebhookPayload('Webhook event is missing an id, type, or object');
        }

        /** @var array<string, mixed> $object */
        return [
            'id' => $id,
            'type' => $type,
            'object' => $object,
        ];
    }

    /**
     * Создает PaymentIntent в Stripe и сохраняет запись Payment в БД.
     */
    public function createPaymentIntent(Order $order): Payment
    {
        $amountInCents = Money::cents($order->total_amount);
        $configuredCurrency = config('services.stripe.currency', 'usd');
        $fallbackCurrency = is_string($configuredCurrency) && $configuredCurrency !== '' ? $configuredCurrency : 'usd';
        $currency = strtolower($order->currency !== '' ? $order->currency : $fallbackCurrency);

        try {
            $intent = $this->client()->paymentIntents->create([
                'amount' => $amountInCents,
                'currency' => $currency,
                'metadata' => [
                    'order_id' => (string) $order->id,
                    'user_id' => (string) $order->user_id,
                ],
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ], [
                'idempotency_key' => 'payment-intent-order-'.$order->id,
            ]);

            return Payment::query()->updateOrCreate(
                ['order_id' => $order->id],
                [
                    'stripe_payment_intent_id' => $intent->id,
                    'stripe_client_secret' => $intent->client_secret,
                    'amount' => $order->total_amount,
                    'currency' => strtoupper($currency),
                    'status' => 'pending',
                    'raw_response' => $intent->toArray(),
                ],
            );
        } catch (ApiErrorException $e) {
            Log::error('Ошибка создания Stripe PaymentIntent', [
                'order_id' => $order->id,
                'exception' => $e,
            ]);

            throw new RuntimeException("Не удалось инициализировать оплату через Stripe: {$e->getMessage()}", 0, $e);
        }
    }

    private function refundResult(Refund $refund): RefundResult
    {
        $raw = $refund->toArray();
        $amount = isset($raw['amount']) && is_int($raw['amount']) && $raw['amount'] > 0
            ? Money::fromCents($raw['amount'])
            : null;
        $currency = isset($raw['currency']) && is_string($raw['currency']) && $raw['currency'] !== ''
            ? strtoupper($raw['currency'])
            : null;

        return match ($refund->status) {
            'succeeded' => new RefundResult(RefundState::Succeeded, (string) $refund->id, $amount, $currency),
            'pending', 'requires_action' => new RefundResult(RefundState::Pending, (string) $refund->id, $amount, $currency),
            default => throw new RuntimeException('Refund failed: '.($refund->failure_reason ?: $refund->id)),
        };
    }

    private function client(): StripeClient
    {
        return ($this->clientResolver)();
    }
}
