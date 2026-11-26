<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Messaging\ConsumerFactory;
use App\Messaging\Consumers\OrderEventsConsumer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Команды склада: резерв, списание и освобождение')]
#[Signature('catalog:consume-stock-commands')]
class ConsumeStockCommandsCommand extends Command
{
    public function handle(OrderEventsConsumer $handler): int
    {
        $topic = (string) config('kafka.topics.stock_commands', 'stock.commands');
        $groupId = config('kafka.consumer_group_id', 'catalog-service-group').'-stock';
        $dlq = (string) config('kafka.dlq.stock_commands', 'stock.commands.dlq.catalog-service');

        ConsumerFactory::consume($topic, $groupId, $dlq, $handler);

        return self::SUCCESS;
    }
}
