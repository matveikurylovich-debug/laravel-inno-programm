<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Kafka\Consumers\InventoryEventsConsumer;
use Illuminate\Console\Command;
use Junges\Kafka\Facades\Kafka;

class ConsumeInventoryEventsCommand extends Command
{
    protected $signature = 'kafka:consume-inventory';

    protected $description = 'Слушает ответы склада и двигает заказ по саге';

    public function handle(InventoryEventsConsumer $handler): int
    {
        $topic = (string) config('kafka.topics.inventory_events', 'inventory.events');
        $groupId = (string) config('kafka.inventory_consumer_group_id', 'order-service-inventory-group');

        $this->info("Запуск консьюмера склада. Топик: {$topic}, Group: {$groupId}");

        Kafka::consumer([$topic])
            ->withConsumerGroupId($groupId)
            ->withHandler($handler)
            ->build()
            ->consume();

        return self::SUCCESS;
    }
}
