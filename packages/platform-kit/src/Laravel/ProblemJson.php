<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Laravel;

use Ecommerce\PlatformKit\Http\HasProblemCode;
use Ecommerce\PlatformKit\Http\ProblemDetails;
use Ecommerce\PlatformKit\Http\ProvidesProblem;
use Ecommerce\PlatformKit\Token\TokenInfrastructureException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ProblemJson
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => self::wants($request),
        );

        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($exception instanceof TokenInfrastructureException || ! self::wants($request)) {
                return null;
            }

            return self::responseFor($exception);
        });
    }

    public static function responseFor(Throwable $exception): JsonResponse
    {
        $customCode = $exception instanceof HasProblemCode ? $exception->problemCode() : null;

        if ($exception instanceof ProvidesProblem) {
            return self::make(
                $exception->problemStatus(),
                ProblemDetails::detail($exception->problemStatus(), $exception->problemDetail()),
                $exception->problemErrors(),
                'about:blank',
                [],
                $customCode,
            );
        }

        if ($exception instanceof ValidationException) {
            $errors = $exception->errors();
            $detail = ProblemDetails::firstError($errors) ?? ProblemDetails::fallback($exception->status);

            return self::make($exception->status, $detail, $errors, 'urn:problem:validation', [], $customCode);
        }

        if ($exception instanceof AuthenticationException) {
            return self::make(401, ProblemDetails::detail(401, $exception->getMessage()), [], 'about:blank', [], $customCode);
        }

        if ($exception instanceof AuthorizationException) {
            $status = $exception->hasStatus() ? $exception->status() : 403;

            return self::make($status, ProblemDetails::detail($status, $exception->getMessage() ?: ''), [], 'about:blank', [], $customCode);
        }

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();

            return self::make($status, ProblemDetails::detail($status, $exception->getMessage()), [], 'about:blank', $exception->getHeaders(), $customCode);
        }

        return self::make(500, ProblemDetails::fallback(500), [], 'about:blank', [], $customCode);
    }

    /**
     * @param  array<string, mixed>  $errors
     * @param  array<string, string>  $headers
     */
    public static function make(int $status, string $detail, array $errors = [], string $type = 'about:blank', array $headers = [], ?string $code = null): JsonResponse
    {
        $headers['Content-Type'] = 'application/problem+json';

        return response()->json(ProblemDetails::make($status, $detail, $errors, $type, $code), $status, $headers);
    }

    private static function wants(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson() || $request->is('webhooks/*');
    }
}
