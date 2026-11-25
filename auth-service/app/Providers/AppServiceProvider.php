<?php

namespace App\Providers;

use App\Auth\RedisRevocationInspector;
use App\Contracts\AuthService;
use App\Enums\RoleName;
use App\Listeners\RevokeSessionsAfterPasswordReset;
use App\Models\User;
use App\Services\JwtService;
use Ecommerce\PlatformKit\Laravel\RevocationInspector;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->requireJwtSecret();
        config(['platform-kit.resolve_eloquent_user' => true]);
        $this->app->singleton(RevocationInspector::class, RedisRevocationInspector::class);
        $this->app->singleton(JwtService::class);
        $this->app->singleton(AuthService::class, fn ($app) => $app->make(JwtService::class));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        Vite::prefetch(concurrency: 3);

        Gate::define('access-admin', fn (User $user) => $user->hasRole(RoleName::Admin));
        Event::listen(PasswordReset::class, RevokeSessionsAfterPasswordReset::class);
    }

    private function requireJwtSecret(): void
    {
        $secret = getenv('JWT_ACCESS_SECRET');
        $command = $_SERVER['argv'][1] ?? '';
        $tooling = ['package:discover', 'config:clear', 'config:cache', 'key:generate', 'about', 'list', 'test'];
        $argv0 = $_SERVER['argv'][0] ?? '';
        if (($secret === false || $secret === '') && ! in_array($command, $tooling, true) && ! str_contains($argv0, 'phpstan')) {
            throw new RuntimeException('JWT-секрет пуст, запустите ./start.sh');
        }
    }
}
