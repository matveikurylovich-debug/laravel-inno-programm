<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\RefreshTokenStore;
use App\Contracts\TokenService;
use App\Listeners\RevokeSessionsAfterPasswordReset;
use App\Services\Auth\JwtService;
use App\Services\Auth\RedisRefreshTokenStore;
use App\Services\Auth\RedisRevocationInspector;
use Ecommerce\PlatformKit\Laravel\RevocationInspector;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->requireJwtSecret();
        config(['platform-kit.resolve_eloquent_user' => true]);
        $this->app->singleton(RefreshTokenStore::class, RedisRefreshTokenStore::class);
        $this->app->singleton(RevocationInspector::class, RedisRevocationInspector::class);
        $this->app->singleton(JwtService::class);
        $this->app->singleton(TokenService::class, JwtService::class);
    }

    public function boot(): void
    {
        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(5)
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('two-factor', fn (Request $request): Limit => Limit::perMinute(30)
            ->by((string) $request->ip()));

        RateLimiter::for('password-reset', fn (Request $request): Limit => Limit::perMinute(5)
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('register', fn (Request $request): Limit => Limit::perMinute(5)
            ->by((string) $request->ip()));

        Event::listen(PasswordReset::class, RevokeSessionsAfterPasswordReset::class);
    }

    private function requireJwtSecret(): void
    {
        $argv = \Illuminate\Support\Facades\Request::server('argv');
        $argv0 = is_array($argv) ? ($argv[0] ?? '') : '';
        $command = is_array($argv) ? ($argv[1] ?? '') : '';
        $tooling = ['package:discover', 'config:clear', 'config:cache', 'key:generate', 'about', 'list', 'test'];
        $path = getenv('JWT_ACCESS_PRIVATE_KEY_PATH');

        if (! in_array($command, $tooling, true) && ! str_contains($argv0, 'phpstan') && ! $this->app->runningInConsole() && (! is_string($path) || $path === '' || ! is_file($path))) {
            throw new RuntimeException('Закрытый ключ access-токена не найден, запустите ./start.sh');
        }
    }
}
