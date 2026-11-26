<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Messaging\ConsumerFactory;
use App\Messaging\Consumers\CatalogEventsConsumer;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Override;

class ConsumeCatalogEventsCommand extends Command
{
    #[Override]
    protected $signature = 'search:consume-catalog-events';

    #[Override]
    protected $description = 'Индексирует события каталога в Elasticsearch';

    public function handle(CatalogEventsConsumer $handler, ConsumerFactory $consumers, Repository $config): int
    {
        $rawTopic = $config->get('kafka.topics.catalog_events', 'catalog.events');
        $rawGroup = $config->get('kafka.consumer_group_id', 'search-service-catalog-group');
        $rawDlq = $config->get('kafka.dlq.catalog_events', 'catalog.events.dlq.search-service');

        $topic = is_scalar($rawTopic) ? (string) $rawTopic : 'catalog.events';
        $group = is_scalar($rawGroup) ? (string) $rawGroup : 'search-service-catalog-group';
        $dlq = is_scalar($rawDlq) ? (string) $rawDlq : 'catalog.events.dlq.search-service';

        $this->info("Слушаем {$topic}, группа {$group}");

        $consumers->run($topic, $group, $dlq, $handler);

        return self::SUCCESS;
    }
}
