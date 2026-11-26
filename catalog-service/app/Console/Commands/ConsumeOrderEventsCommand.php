<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Messaging\ConsumerFactory;
use App\Messaging\Consumers\OrderEventsConsumer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Запуск консьюмера Kafka для обработки событий заказов')]
#[Signature('catalog:consume-order-events')]
class ConsumeOrderEventsCommand extends Command
{
    public function handle(OrderEventsConsumer $handler): int
    {
        $topic = (string) config('kafka.topics.order_events', 'order.events');
        $groupId = (string) config('kafka.consumer_group_id', 'catalog-service-group');
        $dlq = (string) config('kafka.dlq.order_events', 'order.events.dlq.catalog-service');

        $this->info("Запуск Kafka Consumer на топике: [{$topic}] (Группа: {$groupId})...");

        ConsumerFactory::consume($topic, $groupId, $dlq, $handler);

        return self::SUCCESS;
    }
}
