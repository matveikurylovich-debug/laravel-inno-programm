<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Ecommerce\PlatformKit\Laravel\ConsumeKafkaCommand;
use Override;

class ConsumeAuthEventsCommand extends ConsumeKafkaCommand
{
    #[Override]
    protected $signature = 'notification:consume-auth-events';

    #[Override]
    protected $description = 'Запуск консьюмера Kafka для auth-событий';

    protected function consumerKey(): string
    {
        return 'auth-events';
    }

    public function handle(): int
    {
        return parent::handle() === self::SUCCESS ? self::SUCCESS : self::FAILURE;
    }
}
