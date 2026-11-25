<?php

namespace App\Console\Commands;

use App\Models\CatalogOutbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\Message;
use Throwable;

class CatalogOutboxRelayCommand extends Command
{
    protected $signature = 'catalog:outbox-relay {--once : Опубликовать одну пачку и выйти} {--batch=50}';

    protected $description = 'Публикует catalog_outbox в Kafka по порядку id';

    public function handle(): int
    {
        DB::disableQueryLog();

        do {
            $published = $this->publishBatch((int) $this->option('batch'));
            if ($this->option('once')) {
                return self::SUCCESS;
            }
            if ($published === 0) {
                sleep(1);
            }
        } while (true);
    }

    public function publishBatch(int $batch): int
    {
        $rows = CatalogOutbox::query()
            ->whereNull('published_at')
            ->orderBy('id')
            ->limit($batch)
            ->get();

        $count = 0;
        foreach ($rows as $row) {
            try {
                Kafka::publish()
                    ->onTopic($row->topic)
                    ->withMessage(new Message(
                        headers: ['service' => 'catalog-service', 'event-type' => $row->event],
                        body: $row->payload,
                        key: $row->message_key,
                    ))
                    ->send();
                $row->forceFill([
                    'published_at' => now(),
                    'attempts' => $row->attempts + 1,
                ])->save();
                $count++;
            } catch (Throwable $exception) {
                $row->forceFill(['attempts' => $row->attempts + 1])->save();
                report($exception);
                break;
            }
        }

        return $count;
    }
}
