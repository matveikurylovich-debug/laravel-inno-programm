<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CatalogOutbox;
use Ecommerce\PlatformKit\Messaging\EventEnvelope;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\Message;
use Throwable;

#[Description('Публикует catalog_outbox в Kafka по порядку id')]
#[Signature('catalog:relay-outbox {--once : Опубликовать одну пачку и выйти} {--batch=50}')]
class RelayOutboxCommand extends Command
{
    public function handle(): int
    {
        DB::disableQueryLog();

        do {
            $published = $this->publishBatch((int) $this->option('batch'));
            if ($this->option('once')) {
                return self::SUCCESS;
            }
            if ($published === 0) {
                Sleep::sleep(1);
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
                $envelope = EventEnvelope::wrap((string) $row->event, 'catalog-service', $row->payload);
                Kafka::publish()
                    ->onTopic($row->topic)
                    ->withMessage(new Message(
                        headers: array_merge(['service' => 'catalog-service'], $envelope->headers()),
                        body: $envelope->toArray(),
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
