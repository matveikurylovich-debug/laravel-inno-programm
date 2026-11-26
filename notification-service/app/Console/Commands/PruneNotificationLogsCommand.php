<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\NotificationLogRepository;
use Illuminate\Console\Command;
use Override;

class PruneNotificationLogsCommand extends Command
{
    #[Override]
    protected $signature = 'notification:prune-logs {--days= : Количество дней хранения записей журнала}';

    /** @var list<string> */
    #[Override]
    protected $aliases = [
        'notifications:prune',
    ];

    #[Override]
    protected $description = 'Удаляет устаревшие записи журнала уведомлений старше заданного срока хранения';

    public function handle(NotificationLogRepository $logs): int
    {
        $option = $this->option('days');
        $configuredDays = config('notifications.retention_days');
        $days = is_string($option) && ctype_digit($option)
            ? (int) $option
            : (is_int($configuredDays) ? $configuredDays : 90);

        $days = max(1, $days);
        $deleted = $logs->pruneOlderThan(now()->subDays($days));

        $this->info("Удалено устаревших записей уведомлений: {$deleted}.");

        return self::SUCCESS;
    }
}
