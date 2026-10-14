<?php

namespace App\Console\Commands;

use App\Kafka\Consumers\StockEventsConsumer;
use Illuminate\Console\Command;
use Junges\Kafka\Facades\Kafka;

class ConsumeStockEventsCommand extends Command
{
    protected $signature = 'kafka:consume-stock';

    protected $description = 'Обновляет наличие товаров по событиям склада';

    public function handle(StockEventsConsumer $handler): int
    {
        $topic = (string) config('kafka.topics.inventory_events', 'inventory.events');
        $group = (string) config('kafka.stock_consumer_group_id', 'search-service-stock-group');

        $this->info("Слушаем {$topic}, группа {$group}");

        Kafka::consumer([$topic])
            ->withConsumerGroupId($group)
            ->withHandler($handler)
            ->build()
            ->consume();

        return self::SUCCESS;
    }
}
