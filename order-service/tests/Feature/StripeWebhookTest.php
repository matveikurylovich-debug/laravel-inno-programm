<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\Payment;

function stripeSignature(string $payload, string $secret): string
{
    $timestamp = time();
    $signed = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

    return "t={$timestamp},v1={$signed}";
}

beforeEach(function () {
    config(['services.stripe.webhook_secret' => 'whsec_test_secret']);
    mockRedis();
});

it('updates the order from a valid payment failed webhook', function () {
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
    expect($order->status)->toBe('payment_failed')
        ->and($order->payment->status)->toBe('failed');
});

it('rejects an invalid webhook signature', function () {
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
