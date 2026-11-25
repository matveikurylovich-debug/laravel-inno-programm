<?php

namespace App\Console\Commands;

use App\Models\NotificationLog;
use App\Support\SensitivePayload;
use Illuminate\Console\Command;

class RedactNotificationSecretsCommand extends Command
{
    protected $signature = 'notifications:redact-secrets';

    protected $description = 'Удаляет коды 2FA и ссылки сброса пароля из сохранённых уведомлений';

    public function handle(): int
    {
        $result = NotificationLog::raw(function ($collection) {
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

        $unset = is_object($result) && method_exists($result, 'getModifiedCount')
            ? $result->getModifiedCount()
            : 0;

        $rewritten = 0;
        foreach (NotificationLog::query()->cursor() as $log) {
            if (! is_string($log->payload)) {
                continue;
            }

            $decoded = json_decode($log->payload, true);
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
