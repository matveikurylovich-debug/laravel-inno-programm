<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Laravel;

use Ecommerce\PlatformKit\Token\AccessTokenVerifier;
use Ecommerce\PlatformKit\Token\InvalidTokenException;
use Ecommerce\PlatformKit\Token\JwksClient;
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
            return AccessTokenVerifier::fromJwks(
                $this->jwksJson(),
                (string) config('platform-kit.issuer'),
                (string) config('platform-kit.audience'),
            );
        });

        $this->app->singleton(RevocationInspector::class, fn (): RevocationInspector => new NullRevocationInspector);
    }

    public function boot(): void
    {
        $this->registerInfrastructureRendering();
        $this->assertVerificationConfig();

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

    private function assertVerificationConfig(): void
    {
        if ($this->app->runningUnitTests() || $this->app->runningInConsole()) {
            return;
        }

        $path = (string) config('platform-kit.jwks_path');
        $url = (string) config('platform-kit.jwks_url');
        $issuer = (string) config('platform-kit.issuer');
        $audience = (string) config('platform-kit.audience');
        if (($path === '' || ! is_file($path)) && $url === '') {
            throw new TokenInfrastructureException('JWT JWKS path or URL must be configured.');
        }
        if ($issuer === '' || $audience === '') {
            throw new TokenInfrastructureException('JWT issuer and audience must be configured.');
        }
    }

    private function jwksJson(): string
    {
        $path = (string) config('platform-kit.jwks_path');
        if ($path !== '') {
            if (! is_file($path)) {
                throw new TokenInfrastructureException('JWT JWKS file is missing.');
            }

            return (string) file_get_contents($path);
        }

        $cache = (string) config('platform-kit.jwks_cache');
        if ($cache === '') {
            $cache = storage_path('framework/cache/jwks.json');
        }

        return (new JwksClient((string) config('platform-kit.jwks_url'), $cache))->json();
    }
}
