<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('local', 'testing')) {
            return;
        }

        $secret = (string) config('stripe.secret');
        $publishable = (string) config('stripe.publishable');
        $valid = static function (string $value, string $prefix): bool {
            return preg_match('/^'.$prefix.'_[A-Za-z0-9]{16,}$/', $value) === 1
                && ! str_contains($value, 'placeholder');
        };

        if (! $valid($secret, 'sk_(?:test|live)') || ! $valid($publishable, 'pk_(?:test|live)')) {
            throw new RuntimeException('Задайте настоящие STRIPE_SECRET_KEY и STRIPE_PUBLISHABLE_KEY. Плейсхолдеры не подходят.');
        }
    }
}
