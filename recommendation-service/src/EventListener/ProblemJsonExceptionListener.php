<?php

declare(strict_types=1);

namespace App\EventListener;

use Ecommerce\PlatformKit\Http\ProblemDetails;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

// Priority 0 runs after the firewall exception listener (1) and before the HTML error renderer (-128).
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 0)]
final class ProblemJsonExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $path = $event->getRequest()->getPathInfo();
        if (!str_starts_with($path, '/recommendations')) {
            return;
        }

        $throwable = $event->getThrowable();
        $status = 500;
        $detail = ProblemDetails::fallback(500);
        $errors = [];
        $headers = [];

        $violations = $this->violations($throwable);
        if ($violations instanceof ConstraintViolationListInterface) {
            $status = 422;
            $errors = $this->errors($violations);
            $detail = ProblemDetails::firstError($errors) ?? ProblemDetails::fallback(422);
        } elseif ($throwable instanceof AuthenticationException) {
            $status = 401;
            $detail = ProblemDetails::detail(401, $throwable->getMessage());
        } elseif ($throwable instanceof AccessDeniedException) {
            $status = 403;
            $detail = ProblemDetails::detail(403, $throwable->getMessage());
        } elseif ($throwable instanceof HttpExceptionInterface) {
            $status = $throwable->getStatusCode();
            $detail = ProblemDetails::detail($status, $throwable->getMessage());
            $headers = $throwable->getHeaders();
        }

        $headers['Content-Type'] = 'application/problem+json';
        $event->stopPropagation();
        $event->setResponse(new JsonResponse(
            ProblemDetails::make($status, $detail, $errors, 422 === $status ? 'urn:problem:validation' : 'about:blank'),
            $status,
            $headers,
        ));
    }

    private function violations(Throwable $throwable): ?ConstraintViolationListInterface
    {
        $current = $throwable;
        while ($current instanceof Throwable) {
            if ($current instanceof ValidationFailedException) {
                return $current->getViolations();
            }

            $current = $current->getPrevious();
        }

        return null;
    }

    /**
     * @return array<string, list<string>>
     */
    private function errors(ConstraintViolationListInterface $violations): array
    {
        $errors = [];
        foreach ($violations as $violation) {
            $field = '' !== $violation->getPropertyPath() ? $violation->getPropertyPath() : 'request';
            $errors[$field][] = (string) $violation->getMessage();
        }

        return $errors;
    }
}
