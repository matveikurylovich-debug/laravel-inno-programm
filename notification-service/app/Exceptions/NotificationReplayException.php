<?php

declare(strict_types=1);

namespace App\Exceptions;

use Ecommerce\PlatformKit\Http\HasProblemCode;
use Ecommerce\PlatformKit\Http\ProvidesProblem;
use RuntimeException;
use Throwable;

class NotificationReplayException extends RuntimeException implements HasProblemCode, ProvidesProblem
{
    public function __construct(
        string $message,
        private readonly string $problemCodeValue,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function notFailed(): self
    {
        return new self(
            'Повторная отправка доступна только для уведомлений со статусом failed.',
            'notification_not_failed',
        );
    }

    public static function secretRedacted(): self
    {
        return new self(
            'Секретные данные этого уведомления не сохраняются и не подлежат повторной отправке.',
            'notification_secret_redacted',
        );
    }

    public static function unsupportedEvent(string $event): self
    {
        return new self(
            "Событие [{$event}] не поддерживает повторную отправку.",
            'notification_replay_unsupported',
        );
    }

    public static function deliveryFailed(Throwable $previous): self
    {
        return new self(
            'Не удалось повторить отправку уведомления.',
            'notification_replay_failed',
            $previous,
        );
    }

    public function problemStatus(): int
    {
        return 422;
    }

    public function problemDetail(): string
    {
        return $this->getMessage();
    }

    /**
     * @return array<string, mixed>
     */
    public function problemErrors(): array
    {
        return [];
    }

    public function problemCode(): string
    {
        return $this->problemCodeValue;
    }
}
