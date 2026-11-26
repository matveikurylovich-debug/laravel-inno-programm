<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OutboxMessage;
use App\Models\Payment;

function stripeSignature(string $payload, string $secret): string
{
    $timestamp = time();
    $signed = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

    return "t={$timestamp},v1={$signed}";
}

beforeEach(function (): void {
    config(['services.stripe.webhook_secret' => 'whsec_test_secret']);
    mockRedis();
});

it('updates the order from a valid payment failed webhook', function (): void {
    $order = Order::query()->create([
        'user_id' => 1,
        'email' => 'buyer@example.com',
        'store_id' => 1,
        'status' => 'payment_pending',
        'payment_status' => 'pending',
        'total_amount' => 12,
        'currency' => 'usd',
    ]);

    Payment::query()->create([
        'order_id' => $order->id,
        'stripe_payment_intent_id' => 'pi_webhook',
        'amount' => 12,
        'currency' => 'USD',
        'status' => 'pending',
    ]);

    $payload = json_encode([
        'id' => 'evt_failed',
        'object' => 'event',
        'type' => 'payment_intent.payment_failed',
        'data' => [
            'object' => [
                'id' => 'pi_webhook',
                'object' => 'payment_intent',
                'metadata' => [
                    'order_id' => $order->id,
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $this->call(
        'POST',
        '/webhooks/stripe',
        [],
        [],
        [],
        [
            'HTTP_STRIPE_SIGNATURE' => stripeSignature($payload, 'whsec_test_secret'),
            'CONTENT_TYPE' => 'application/json',
        ],
        $payload,
    )->assertOk();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::PaymentPending)
        ->and($order->payment_status)->toBe(PaymentStatus::Failed)
        ->and($order->payment->status)->toBe(PaymentStatus::Failed);
});

it('rejects an invalid webhook signature', function (): void {
    $payload = '{"id":"evt_bad","object":"event","type":"payment_intent.payment_failed","data":{"object":{"id":"pi_x","object":"payment_intent"}}}';

    $this->call(
        'POST',
        '/webhooks/stripe',
        [],
        [],
        [],
        [
            'HTTP_STRIPE_SIGNATURE' => 't=1,v1=deadbeef',
            'CONTENT_TYPE' => 'application/json',
        ],
        $payload,
    )->assertStatus(400);
});

it('processes a webhook event only once', function (): void {
    $order = Order::query()->create([
        'user_id' => 1,
        'email' => 'buyer@example.com',
        'store_id' => 1,
        'status' => 'payment_pending',
        'payment_status' => 'pending',
        'total_amount' => 12,
        'currency' => 'usd',
    ]);
    Payment::query()->create([
        'order_id' => $order->id,
        'stripe_payment_intent_id' => 'pi_once',
        'amount' => 12,
        'currency' => 'USD',
        'status' => 'pending',
    ]);
    $payload = json_encode([
        'id' => 'evt_once',
        'object' => 'event',
        'type' => 'payment_intent.payment_failed',
        'data' => ['object' => ['id' => 'pi_once', 'object' => 'payment_intent', 'metadata' => ['order_id' => $order->id]]],
    ], JSON_THROW_ON_ERROR);

    $this->call('POST', '/webhooks/stripe', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => stripeSignature($payload, 'whsec_test_secret'),
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertOk();
    $this->call('POST', '/webhooks/stripe', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => stripeSignature($payload, 'whsec_test_secret'),
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertOk()->assertJsonPath('status', 'duplicate');

    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Failed);
});

it('moves a pending refund to refunded when stripe reports success', function (): void {
    $order = Order::query()->create([
        'user_id' => 1,
        'email' => 'buyer@example.com',
        'store_id' => 1,
        'status' => 'cancelled',
        'payment_status' => 'refund_pending',
        'total_amount' => 12,
        'currency' => 'usd',
    ]);
    Payment::query()->create([
        'order_id' => $order->id,
        'stripe_payment_intent_id' => 'pi_refund',
        'stripe_refund_id' => 're_1',
        'amount' => 12,
        'currency' => 'USD',
        'status' => 'succeeded',
    ]);
    $payload = json_encode([
        'id' => 'evt_refund',
        'object' => 'event',
        'type' => 'refund.updated',
        'data' => ['object' => [
            'id' => 're_1',
            'object' => 'refund',
            'status' => 'succeeded',
            'payment_intent' => 'pi_refund',
        ]],
    ], JSON_THROW_ON_ERROR);

    $this->call('POST', '/webhooks/stripe', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => stripeSignature($payload, 'whsec_test_secret'),
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertOk();

    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Refunded);
    $refundedEvent = OutboxMessage::query()->where('event_type', 'order.refunded')->first();
    expect($refundedEvent)->not->toBeNull()
        ->and($refundedEvent->payload['order_id'])->toBe($order->id)
        ->and($refundedEvent->payload['refunded_amount'])->toBe('12.00')
        ->and($refundedEvent->payload['currency'])->toBe('USD');
});
