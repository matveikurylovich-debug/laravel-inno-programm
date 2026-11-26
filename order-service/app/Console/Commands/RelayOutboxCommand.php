<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Outbox\OutboxRelay;
use Illuminate\Console\Command;
use Override;

class RelayOutboxCommand extends Command
{
    #[Override]
    protected $signature = 'order:relay-outbox
                            {--batch=50 : Сколько сообщений брать за раз}
                            {--sleep=1 : Пауза в секундах, если нет новых сообщений}
                            {--once : Обработать одну пачку и завершиться}';

    #[Override]
    protected $description = 'Публикует неотправленные события из outbox_messages в брокер Apache Kafka';

    public function handle(OutboxRelay $relay): int
    {
        $this->trap([SIGTERM, SIGINT], function () use ($relay): void {
            $relay->stop();
        });

        $topic = (string) config('kafka.topics.order_events', 'order.events');
        $this->info("Outbox Relay запущен. Публикация в топик: {$topic}");

        $relay->run(
            (int) $this->option('batch'),
            (int) $this->option('sleep'),
            (bool) $this->option('once'),
        );

        return self::SUCCESS;
    }
}
