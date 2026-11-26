<?php

declare(strict_types=1);

use App\Contracts\PaymentGateway;
use App\Integrations\Stripe\StripePaymentGateway;
use App\Models\Order;
use Stripe\PaymentIntent;
use Stripe\Service\PaymentIntentService;
use Stripe\StripeClient;

it('resolves StripePaymentGateway with a container-swapped StripeClient without new StripeClient', function (): void {
    config([
        'services.stripe.secret' => '',
        'services.stripe.publishable' => 'pk_test_1234567890abcdef1234',
        'services.stripe.currency' => 'usd',
    ]);

    $intent = PaymentIntent::constructFrom([
        'id' => 'pi_container_mock',
        'object' => 'payment_intent',
        'client_secret' => 'pi_container_mock_secret',
        'status' => 'requires_payment_method',
        'amount' => 2500,
        'currency' => 'usd',
    ]);

    $paymentIntents = Mockery::mock(PaymentIntentService::class);
    $paymentIntents->shouldReceive('create')
        ->once()
        ->andReturn($intent);

    $stripeClient = Mockery::mock(StripeClient::class);
    $stripeClient->shouldReceive('getService')
        ->with('paymentIntents')
        ->andReturn($paymentIntents);

    $this->app->instance(StripeClient::class, $stripeClient);

    $order = Order::query()->create([
        'user_id' => 7,
        'email' => 'di@example.com',
        'store_id' => 1,
        'status' => 'stock_reserved',
        'payment_status' => 'pending',
        'total_amount' => 25.00,
        'currency' => 'usd',
    ]);

    $gateway = resolve(PaymentGateway::class);
    expect($gateway)->toBeInstanceOf(StripePaymentGateway::class);

    $payment = $gateway->createPaymentIntent($order);

    expect($payment->stripe_payment_intent_id)->toBe('pi_container_mock')
        ->and($payment->stripe_client_secret)->toBe('pi_container_mock_secret')
        ->and($payment->publishable_key)->toBe('pk_test_1234567890abcdef1234');
});

it('throws RuntimeException only when StripeClient is actually resolved with an empty secret', function (): void {
    config(['services.stripe.secret' => '']);

    $gateway = resolve(PaymentGateway::class);
    expect($gateway)->toBeInstanceOf(StripePaymentGateway::class);

    expect(fn () => resolve(StripeClient::class))
        ->toThrow(RuntimeException::class, 'Stripe secret key is not configured');
});

it('uses the api jwt guard as the default authentication guard', function (): void {
    expect(config('auth.defaults.guard'))->toBe('api');
});
