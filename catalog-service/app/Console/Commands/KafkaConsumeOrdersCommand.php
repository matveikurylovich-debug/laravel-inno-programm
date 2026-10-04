<?php

namespace App\Console\Commands;

use App\Kafka\Consumers\OrderEventsHandler;
use Illuminate\Console\Command;
use Junges\Kafka\Facades\Kafka;

class KafkaConsumeOrdersCommand extends Command
{
    protected $signature = 'kafka:consume-orders';

    protected $description = 'Запуск консьюмера Kafka для обработки событий заказов';

    public function handle(OrderEventsHandler $handler): int
    {
        $topic = (string) config('kafka.topics.order_events', 'order.events');
        $groupId = (string) config('kafka.consumer_group_id', 'catalog-service-group');

        $this->info("Запуск Kafka Consumer на топике: [{$topic}] (Группа: {$groupId})...");

        Kafka::consumer([$topic])
            ->withConsumerGroupId($groupId)
            ->withAutoCommit()
            ->withHandler($handler)
            ->build()
            ->consume();

        return self::SUCCESS;
    }
}
