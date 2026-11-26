<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\NotificationLogRepository;
use Illuminate\Console\Command;
use Override;

class ConvertNotificationPayloadsCommand extends Command
{
    #[Override]
    protected $signature = 'notifications:convert-payloads';

    /** @var list<string> */
    #[Override]
    protected $aliases = [
        'notification:convert-payloads',
    ];

    #[Override]
    protected $description = 'Конвертирует устаревшие строковые JSON-поля payload в нативные BSON-документы';

    public function handle(NotificationLogRepository $logs): int
    {
        $result = $logs->convertStringPayloads();

        $this->info("Сконвертировано строковых payload: {$result['converted']}, ошибок декодирования: {$result['failed']}.");

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
