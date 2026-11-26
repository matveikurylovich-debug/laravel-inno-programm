<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Exceptions\NonRetryableException;
use Ecommerce\PlatformKit\Messaging\NonRetryableMessageException;
use Illuminate\Support\Sleep;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\MessageConsumer;
use Junges\Kafka\Facades\Kafka;
use Throwable;

class ConsumerFactory
{
    public static function consume(string $topic, string $group, string $dlq, callable $handler): void
    {
        $retries = max(0, (int) config('kafka.failed_message_retries', 5));
        $backoffMs = max(0, (int) config('kafka.failed_message_backoff_ms', 2000));

        Kafka::consumer([$topic])
            ->withBrokers((string) config('kafka.brokers'))
            ->withConsumerGroupId($group)
            ->withManualCommit()
            ->withOptions([
                'auto.offset.reset' => (string) config('kafka.offset_reset', 'earliest'),
            ])
            ->withDlq($dlq)
            ->withHandler(static function (ConsumerMessage $message, MessageConsumer $consumer) use ($handler, $retries, $backoffMs): void {
                $attempt = 0;

                while (true) {
                    try {
                        $handler($message, $consumer);
                        $consumer->commit($message);

                        return;
                    } catch (NonRetryableException|NonRetryableMessageException $exception) {
                        throw $exception;
                    } catch (Throwable $exception) {
                        if ($attempt >= $retries) {
                            throw $exception;
                        }

                        $attempt++;
                        if ($backoffMs > 0) {
                            Sleep::usleep($backoffMs * 1000);
                        }
                    }
                }
            })
            ->build()
            ->consume();
    }
}
