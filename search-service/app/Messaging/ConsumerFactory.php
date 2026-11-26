<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Exceptions\NonRetryableException;
use Ecommerce\PlatformKit\Messaging\NonRetryableMessageException;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Sleep;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\MessageConsumer;
use Junges\Kafka\Facades\Kafka;
use Throwable;

class ConsumerFactory
{
    public function __construct(
        #[Config('kafka.brokers', 'kafka:9092')]
        private readonly string $brokers = 'kafka:9092',
        #[Config('kafka.offset_reset', 'earliest')]
        private readonly string $offsetReset = 'earliest',
        #[Config('kafka.failed_message_retries', 5)]
        private readonly int $retries = 5,
        #[Config('kafka.failed_message_backoff_ms', 2000)]
        private readonly int $backoffMs = 2000,
    ) {}

    /**
     * @param  callable(ConsumerMessage, MessageConsumer): void  $handler
     */
    public static function consume(string $topic, string $group, string $dlq, callable $handler): void
    {
        resolve(self::class)->run($topic, $group, $dlq, $handler);
    }

    /**
     * @param  callable(ConsumerMessage, MessageConsumer): void  $handler
     */
    public function run(string $topic, string $group, string $dlq, callable $handler): void
    {
        $retries = max(0, $this->retries);
        $backoffMs = max(0, $this->backoffMs);

        Kafka::consumer([$topic])
            ->withBrokers($this->brokers)
            ->withConsumerGroupId($group)
            ->withManualCommit()
            ->withOptions([
                'auto.offset.reset' => $this->offsetReset,
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
