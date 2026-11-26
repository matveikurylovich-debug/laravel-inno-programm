<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Exceptions\NonRetryableException;
use Throwable;

/**
 * Повтор инфраструктурной ошибки без коммита offset, затем DLQ.
 * Невалидный payload уходит в DLQ сразу.
 */
class FailedMessagePolicy
{
    public function __construct(private readonly int $retries = 5) {}

    public function deliver(callable $handler, callable $commit, callable $dlq): string
    {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $handler();
                $commit();

                return 'committed';
            } catch (NonRetryableException $exception) {
                $dlq($exception);
                $commit();

                return 'dlq';
            } catch (Throwable $exception) {
                if ($attempt > $this->retries) {
                    $dlq($exception);
                    $commit();

                    return 'dlq';
                }
            }
        }
    }
}
