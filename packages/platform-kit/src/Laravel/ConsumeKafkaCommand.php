<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Laravel;

use Ecommerce\PlatformKit\Messaging\NonRetryableMessageException;
use Illuminate\Console\Command;
use Illuminate\Support\Sleep;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Contracts\MessageConsumer;
use Junges\Kafka\Facades\Kafka;
use Throwable;

abstract class ConsumeKafkaCommand extends Command
{
    /**
     * Key in config('kafka.consumers').
     */
    abstract protected function consumerKey(): string;

    public function handle(): int
    {
        /** @var array{topics: list<string>, group: string, handler: class-string, dlq: string, retries?: int, retry_backoff_ms?: int}|null $cfg */
        $cfg = config('kafka.consumers.'.$this->consumerKey());
        if (! is_array($cfg)) {
            $this->error("Kafka consumer [{$this->consumerKey()}] is not configured.");

            return self::FAILURE;
        }

        $topics = array_map(
            static fn (string $topicKey): string => (string) config("kafka.topics.{$topicKey}", $topicKey),
            $cfg['topics'],
        );

        /** @var callable(ConsumerMessage, MessageConsumer): void $handler */
        $handler = $this->laravel->make($cfg['handler']);
        $retries = max(0, (int) ($cfg['retries'] ?? config('kafka.consumer_retries', 5)));
        $backoffMs = max(0, (int) ($cfg['retry_backoff_ms'] ?? config('kafka.consumer_retry_backoff_ms', 2000)));

        Kafka::consumer($topics)
            ->withBrokers((string) config('kafka.brokers'))
            ->withConsumerGroupId($cfg['group'])
            ->withManualCommit()
            ->withOptions(['auto.offset.reset' => (string) config('kafka.offset_reset', 'earliest')])
            ->withDlq($cfg['dlq'])
            ->withHandler(static function (ConsumerMessage $message, MessageConsumer $consumer) use ($handler, $retries, $backoffMs): void {
                $attempt = 0;

                while (true) {
                    try {
                        $handler($message, $consumer);
                        $consumer->commit($message);

                        return;
                    } catch (NonRetryableMessageException $exception) {
                        throw $exception;
                    } catch (Throwable $exception) {
                        if (str_ends_with($exception::class, 'NonRetryableException') || $attempt >= $retries) {
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

        return self::SUCCESS;
    }
}
