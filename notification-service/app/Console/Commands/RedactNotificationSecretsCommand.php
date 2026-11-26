<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\NotificationLogRepository;
use Illuminate\Console\Command;
use Override;

class RedactNotificationSecretsCommand extends Command
{
    #[Override]
    protected $signature = 'notification:redact-secrets';

    /** @var list<string> */
    #[Override]
    protected $aliases = [
        'notifications:purge-secrets',
        'notifications:redact-secrets',
    ];

    #[Override]
    protected $description = 'Удаляет коды 2FA и ссылки сброса пароля из сохранённых уведомлений ($unset payload.data.code и payload.data.reset_url)';

    public function handle(NotificationLogRepository $logs): int
    {
        $result = $logs->purgeSecrets();

        $this->info("Очищено записей: {$result['unset']}, переписано строковых payload: {$result['rewritten']}.");

        return self::SUCCESS;
    }
}
