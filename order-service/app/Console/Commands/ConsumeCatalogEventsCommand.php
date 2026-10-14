<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Kafka\Consumers\CatalogEventsConsumer;
use Illuminate\Console\Command;
use Junges\Kafka\Facades\Kafka;

class ConsumeCatalogEventsCommand extends Command
{
    protected $signature = 'kafka:consume-catalog';

    protected $description = 'Слушает события каталога и обновляет таблицу product_snapshots';

    public function handle(): int
    {
        $topic = config('kafka.topics.catalog_events', 'catalog.events');
        $groupId = config('kafka.consumer_group_id', 'order-service-catalog-group');

        $this->info("Запуск консьюмера событий каталога. Топик: {$topic}, Group: {$groupId}");

        $consumer = Kafka::consumer([$topic])
            ->withConsumerGroupId($groupId)
            ->withHandler(new CatalogEventsConsumer)
            ->build();

        $consumer->consume();

        return Command::SUCCESS;
    }
}
