<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\OutboxMessage;
use Illuminate\Console\Command;
use Override;

class OutboxRetryCommand extends Command
{
    #[Override]
    protected $signature = 'order:retry-outbox {--failed : Вернуть dead-сообщения в очередь}';

    #[Override]
    protected $description = 'Возвращает невалидные outbox-сообщения из dead в pending';

    public function handle(): int
    {
        if (! $this->option('failed')) {
            $this->error('Укажите --failed');

            return self::FAILURE;
        }

        $count = OutboxMessage::query()->where('status', 'dead')->update([
            'status' => 'pending',
            'next_retry_at' => now(),
            'error_message' => null,
        ]);

        $this->info("Возвращено в очередь: {$count}");

        return self::SUCCESS;
    }
}
