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
        $secret = getenv('JWT_ACCESS_SECRET');
        $command = $_SERVER['argv'][1] ?? '';
        $tooling = ['package:discover', 'config:clear', 'config:cache', 'key:generate', 'about', 'list', 'test'];
        $argv0 = $_SERVER['argv'][0] ?? '';
        if (($secret === false || $secret === '') && ! in_array($command, $tooling, true) && ! str_contains($argv0, 'phpstan')) {
            throw new RuntimeException('JWT-секрет пуст, запустите ./start.sh');
        }
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
