<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\Message;
use Override;

class DlqReplayCommand extends Command
{
    #[Override]
    protected $signature = 'search:replay-dlq {topic} {--replay : Вернуть сообщения в исходный топик}';

    #[Override]
    protected $description = 'Показывает сообщения DLQ и по флагу --replay публикует их в исходный топик';

    public function handle(): int
    {
        $topic = $this->argument('topic');
        $source = self::sourceTopic($topic);
        $replay = (bool) $this->option('replay');

        $this->info($replay ? "Переигрываем {$topic} → {$source}" : "Читаем {$topic}");

        Kafka::consumer([$topic])
            ->withConsumerGroupId('dlq-replay-'.substr(sha1($topic), 0, 12))
            ->withOptions(['auto.offset.reset' => 'earliest'])
            ->stopAfterLastMessage()
            ->withHandler(function (ConsumerMessage $message) use ($replay, $source): void {
                $body = $message->getBody();
                $encoded = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $this->line(is_string($encoded) ? $encoded : '{}');

                if (! $replay) {
                    return;
                }

                Kafka::publish()
                    ->onTopic($source)
                    ->withMessage(new Message(
                        body: is_array($body) ? $body : ['payload' => $body],
                        key: is_string($message->getKey()) ? $message->getKey() : null,
                    ))
                    ->send();
            })
            ->build()
            ->consume();

        return self::SUCCESS;
    }

    public static function sourceTopic(string $dlqTopic): string
    {
        $position = strpos($dlqTopic, '.dlq.');

        if ($position === false) {
            throw new InvalidArgumentException("Топик {$dlqTopic} не является DLQ");
        }

        return substr($dlqTopic, 0, $position);
    }
}
