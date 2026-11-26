<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Integrations\Stripe\StripePaymentGateway;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(StripeClient::class, static function (): StripeClient {
            $secret = config('services.stripe.secret');
            $secretKey = is_string($secret) ? $secret : '';

            if ($secretKey === '') {
                throw new RuntimeException('Stripe secret key is not configured');
            }

            return new StripeClient($secretKey);
        });

        $this->app->bind(
            StripePaymentGateway::class,
            static fn (Application $app): StripePaymentGateway => new StripePaymentGateway(
                static fn (): StripeClient => $app->make(StripeClient::class),
            ),
        );

        $this->app->bind(PaymentGateway::class, StripePaymentGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('local', 'testing')) {
            return;
        }

        $rawSecret = config('services.stripe.secret');
        $rawPublishable = config('services.stripe.publishable');
        $secret = is_string($rawSecret) ? trim($rawSecret) : '';
        $publishable = is_string($rawPublishable) ? trim($rawPublishable) : '';

        if ($secret === '' && $publishable === '') {
            return;
        }

        $valid = static fn (string $value, string $prefix): bool => preg_match('/^'.$prefix.'_[A-Za-z0-9]{16,}$/', $value) === 1
            && ! str_contains($value, 'placeholder');

        if (! $valid($secret, 'sk_(?:test|live)') || ! $valid($publishable, 'pk_(?:test|live)')) {
            throw new RuntimeException('Задайте настоящие STRIPE_SECRET_KEY и STRIPE_PUBLISHABLE_KEY. Плейсхолдеры не подходят.');
        }
    }
}
