<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Laravel;

use Ecommerce\PlatformKit\Token\AccessTokenVerifier;
use Ecommerce\PlatformKit\Token\InvalidTokenException;
use Ecommerce\PlatformKit\Token\TokenInfrastructureException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

final class PlatformKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/platform-kit.php', 'platform-kit');

        $this->app->singleton(AccessTokenVerifier::class, function (): AccessTokenVerifier {
            return new AccessTokenVerifier(
                secret: (string) config('platform-kit.access_secret'),
                algorithm: (string) config('platform-kit.algorithm', 'HS256'),
            );
        });

        $this->app->singleton(RevocationInspector::class, fn (): RevocationInspector => new NullRevocationInspector);
    }

    public function boot(): void
    {
        $this->registerInfrastructureRendering();
        $this->assertAccessSecret();

        $this->app->make('auth')->extend('jwt', function ($app, string $_name, array $config) {
            $guard = new JwtGuard(function (Request $request) use ($app) {
                $token = $request->bearerToken();
                if (! is_string($token) || $token === '') {
                    return null;
                }

                try {
                    $claims = $app->make(AccessTokenVerifier::class)->verify($token);
                    $app->make(RevocationInspector::class)->assertActive($claims, $token);
                } catch (InvalidTokenException) {
                    return null;
                } catch (TokenInfrastructureException $exception) {
                    throw $exception;
                }

                if (config('platform-kit.resolve_eloquent_user') === true) {
                    $model = config('auth.providers.users.model');
                    if (is_string($model) && class_exists($model)) {
                        return $model::query()->find($claims->userId);
                    }
                }

                return new JwtUser($claims);
            }, $app['request'], $app->make('auth')->createUserProvider($config['provider'] ?? null));

            $app->refresh('request', $guard, 'setRequest');

            return $guard;
        });
    }

    private function registerInfrastructureRendering(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);
        if (! method_exists($handler, 'renderable')) {
            return;
        }

        $handler->renderable(function (TokenInfrastructureException $exception, Request $request) {
            report($exception);
            logger()->error('JWT infrastructure failure', ['exception' => $exception]);

            return response()->json(['message' => 'Server Error'], 500);
        });
    }

    private function assertAccessSecret(): void
    {
        if ($this->app->runningUnitTests()) {
            return;
        }

        $command = $_SERVER['argv'][1] ?? '';
        $tooling = ['package:discover', 'config:clear', 'config:cache', 'key:generate', 'about', 'list', 'test'];
        $argv0 = (string) ($_SERVER['argv'][0] ?? '');
        if (in_array($command, $tooling, true) || str_contains($argv0, 'phpstan')) {
            return;
        }

        $secret = (string) config('platform-kit.access_secret', '');
        if (strlen($secret) < 32) {
            throw new TokenInfrastructureException('JWT access secret must be at least 32 bytes.');
        }
    }
}
