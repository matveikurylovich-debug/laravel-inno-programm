<?php

namespace App\Console\Commands;

use App\Models\NotificationLog;
use App\Support\SensitivePayload;
use Illuminate\Console\Command;
use MongoDB\Collection;
use MongoDB\Laravel\Eloquent\Builder;
use MongoDB\UpdateResult;

class RedactNotificationSecretsCommand extends Command
{
    protected $signature = 'notifications:purge-secrets';

    protected $description = 'Удаляет коды 2FA и ссылки сброса пароля из сохранённых уведомлений';

    public function handle(): int
    {
        /** @var Builder<NotificationLog> $logs */
        $logs = NotificationLog::query();
        $result = $logs->raw(function (Collection $collection): UpdateResult {
            return $collection->updateMany(
                [],
                ['$unset' => [
                    'payload.data.code' => '',
                    'payload.data.reset_url' => '',
                    'payload.code' => '',
                    'payload.reset_url' => '',
                ]],
            );
        });

        $unset = $result->getModifiedCount();

        $rewritten = 0;
        foreach (NotificationLog::query()->cursor() as $log) {
            $raw = $log->getAttributes()['payload'] ?? null;
            if (! is_string($raw)) {
                continue;
            }

            $decoded = json_decode($raw, true);
            if (! is_array($decoded)) {
                continue;
            }

            $log->payload = SensitivePayload::redact($decoded);
            $log->save();
            $rewritten++;
        }

        $this->info("Redacted {$unset} notification logs, rewrote {$rewritten} string payloads.");

        return self::SUCCESS;
    }
}
