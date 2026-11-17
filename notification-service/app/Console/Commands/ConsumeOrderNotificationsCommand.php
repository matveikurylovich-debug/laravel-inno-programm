<?php

namespace App\Console\Commands;

use App\Kafka\Handlers\OrderNotificationHandler;
use Illuminate\Console\Command;
use Junges\Kafka\Facades\Kafka;

class ConsumeOrderNotificationsCommand extends Command
{
    protected $signature = 'kafka:consume-orders';

    protected $description = 'Consume order notifications from Kafka topic [order.events]';

    public function handle(OrderNotificationHandler $handler): int
    {
        $topic = (string) config('kafka.topics.order_events', 'order.events');
        $this->info("Starting Kafka consumer for order notifications on [{$topic}]...");

        Kafka::consumer([$topic])
            ->withConsumerGroupId('order-notification-service-group')
            ->withAutoCommit()
            ->withHandler($handler)
            ->build()
            ->consume();

        return self::SUCCESS;
    }
}
