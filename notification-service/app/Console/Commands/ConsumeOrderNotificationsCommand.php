<?php

namespace App\Console\Commands;

use App\Kafka\Handlers\OrderNotificationHandler;
use Illuminate\Console\Command;
use Junges\Kafka\Facades\Kafka;

class ConsumeOrderNotificationsCommand extends Command
{
    protected $signature = 'kafka:consume-orders';

    protected $description = 'Consume order notifications from Kafka topic [order.notifications]';

    public function handle(OrderNotificationHandler $handler): int
    {
        $this->info('Starting Kafka consumer for order notifications...');

        $consumer = Kafka::consumer(['order.notifications'])
            ->withConsumerGroupId('order-notification-service-group')
            ->withAutoCommit()
            ->withHandler($handler)
            ->build()
            ->consume();

        return 0;
    }
}
