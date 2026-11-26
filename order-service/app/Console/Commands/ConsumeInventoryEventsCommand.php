<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Messaging\ConsumerFactory;
use App\Messaging\Consumers\InventoryEventsConsumer;
use Illuminate\Console\Command;
use Override;

class ConsumeInventoryEventsCommand extends Command
{
    #[Override]
    protected $signature = 'order:consume-inventory-events';

    #[Override]
    protected $description = 'Слушает ответы склада и двигает заказ по саге';

    public function handle(InventoryEventsConsumer $handler): int
    {
        $topic = (string) config('kafka.topics.inventory_events', 'inventory.events');
        $groupId = (string) config('kafka.inventory_consumer_group_id', 'order-service-inventory-group');
        $dlq = (string) config('kafka.dlq.inventory_events', 'inventory.events.dlq.order-service');

        $this->info("Запуск консьюмера склада. Топик: {$topic}, Group: {$groupId}");

        ConsumerFactory::consume($topic, $groupId, $dlq, $handler);

        return self::SUCCESS;
    }
}
