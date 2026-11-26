<?php

declare(strict_types=1);

namespace App\Security;

use Ecommerce\PlatformKit\Http\ProblemDetails;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class ProblemJsonAuthenticationFailureHandler implements AuthenticationEntryPointInterface, AuthenticationFailureHandlerInterface
{
    public function start(Request $request, ?AuthenticationException $authException = null): JsonResponse
    {
        $message = $authException instanceof AuthenticationException ? $authException->getMessage() : '';

        return $this->unauthorized($message);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        return $this->unauthorized($exception->getMessage());
    }

    private function unauthorized(string $message): JsonResponse
    {
        $detail = ProblemDetails::detail(401, $message);
        if ('Full authentication is required to access this resource.' === $detail) {
            $detail = 'Требуется авторизация.';
        }

        return new JsonResponse(
            ProblemDetails::make(401, $detail),
            \Symfony\Component\HttpFoundation\Response::HTTP_UNAUTHORIZED,
            ['Content-Type' => 'application/problem+json'],
        );
    }
}
