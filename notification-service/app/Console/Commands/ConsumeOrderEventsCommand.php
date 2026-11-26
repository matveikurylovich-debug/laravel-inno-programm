<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Ecommerce\PlatformKit\Laravel\ConsumeKafkaCommand;
use Override;

class ConsumeOrderEventsCommand extends ConsumeKafkaCommand
{
    #[Override]
    protected $signature = 'notification:consume-order-events';

    #[Override]
    protected $description = 'Запуск консьюмера Kafka для order-событий';

    protected function consumerKey(): string
    {
        return 'order-events';
    }

    public function handle(): int
    {
        return parent::handle() === self::SUCCESS ? self::SUCCESS : self::FAILURE;
    }
}
