<?php

namespace App\Console\Commands;

use App\Kafka\Handlers\AuthNotificationHandler;
use Illuminate\Console\Command;
use Junges\Kafka\Facades\Kafka;

class ConsumeAuthNotificationsCommand extends Command
{
    protected $signature = 'kafka:consume-auth';

    protected $description = 'Запуск консьюмера Kafka для обработки auth-уведомлений';

    public function handle(AuthNotificationHandler $handler)
    {

        $this->info('Starting Kafka consumer for auth notifications...');

        $consumer = Kafka::consumer(['auth.notifications'])
            ->withConsumerGroupId('notification-service-group')
            ->withAutoCommit()
            ->withHandler($handler)
            ->build()
            ->consume();

        return 0;
    }
}
