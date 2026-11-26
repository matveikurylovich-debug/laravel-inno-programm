<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Messaging\ConsumerFactory;
use App\Messaging\Consumers\CatalogEventsConsumer;
use Illuminate\Console\Command;
use Override;

class ConsumeCatalogEventsCommand extends Command
{
    #[Override]
    protected $signature = 'order:consume-catalog-events';

    #[Override]
    protected $description = 'Слушает события каталога и обновляет таблицу product_snapshots';

    public function handle(CatalogEventsConsumer $handler): int
    {
        $topic = (string) config('kafka.topics.catalog_events', 'catalog.events');
        $groupId = (string) config('kafka.consumer_group_id', 'order-service-catalog-group');
        $dlq = (string) config('kafka.dlq.catalog_events', 'catalog.events.dlq.order-service');

        $this->info("Запуск консьюмера событий каталога. Топик: {$topic}, Group: {$groupId}");

        ConsumerFactory::consume($topic, $groupId, $dlq, $handler);

        return self::SUCCESS;
    }
}
