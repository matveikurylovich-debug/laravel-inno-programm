<?php

namespace App\Console\Commands;

use App\Kafka\Consumers\CatalogEventsConsumer;
use Illuminate\Console\Command;
use Junges\Kafka\Facades\Kafka;

class ConsumeCatalogEventsCommand extends Command
{
    protected $signature = 'kafka:consume-catalog';

    protected $description = 'Индексирует события каталога в Elasticsearch';

    public function handle(CatalogEventsConsumer $handler): int
    {
        $topic = (string) config('kafka.topics.catalog_events', 'catalog.events');
        $group = (string) config('kafka.consumer_group_id', 'search-service-catalog-group');

        $this->info("Слушаем {$topic}, группа {$group}");

        Kafka::consumer([$topic])
            ->withConsumerGroupId($group)
            ->withHandler($handler)
            ->build()
            ->consume();

        return self::SUCCESS;
    }
}
