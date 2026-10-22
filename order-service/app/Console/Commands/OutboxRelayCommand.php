<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\OutboxMessage;
use App\Services\Analytics\AnalyticsEventPublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\Message;
use Throwable;

class OutboxRelayCommand extends Command
{
    private const MAX_RETRIES = 5;

    // Имя команды в терминале и параметры (размер пачки и пауза)
    protected $signature = 'order:outbox-relay 
                            {--batch=50 : Сколько сообщений брать за раз} 
                            {--sleep=1 : Пауза в секундах, если нет новых сообщений}
                            {--once : Обработать одну пачку и завершиться}';

    protected $description = 'Публикует неотправленные события из outbox_messages в брокер Apache Kafka';

    public function handle(): int
    {
        $batchSize = (int) $this->option('batch');
        $sleepSeconds = (int) $this->option('sleep');
        $once = (bool) $this->option('once');
        $topic = config('kafka.topics.order_events', 'order.events');

        $this->info("Outbox Relay запущен. Публикация в топик: {$topic}");

        // Отключаем накопление логов SQL в памяти, чтобы воркер не съедал RAM при долгой работе
        DB::disableQueryLog();

        // Бесконечный цикл: воркер работает как демон
        while (true) {
            $processedCount = $this->processBatch($batchSize, $topic);

            if ($once) {
                return self::SUCCESS;
            }

            // Если новых сообщений не было — отдыхаем заданное количество секунд
            if ($processedCount === 0) {
                sleep($sleepSeconds);
            }
        }
    }

    private function processBatch(int $batchSize, string $topic): int
    {
        // 1. Забираем пачку самых старых сообщений, у которых подошло время отправки
        $messages = OutboxMessage::query()
            ->where('status', 'pending')
            ->where(function ($query): void {
                $query->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            })
            ->orderBy('id', 'asc')
            ->limit($batchSize)
            ->get();

        if ($messages->isEmpty()) {
            return 0;
        }

        // 2. Отправляем каждое по очереди
        foreach ($messages as $outboxMessage) {
            $this->publishMessage($outboxMessage, $topic);
        }

        return $messages->count();
    }

    private function publishMessage(OutboxMessage $outboxMessage, string $topic): void
    {
        try {
            // Формируем сообщение Kafka
            $kafkaMessage = new Message(
                headers: [
                    'event-type' => $outboxMessage->event_type,
                    'source' => 'order-service',
                ],
                body: array_merge($outboxMessage->payload, [
                    'event' => $outboxMessage->event_type,
                ]),
                key: (string) $outboxMessage->aggregate_id,
                partition: -1,
            );

            // Сетевой вызов: отправка в брокер
            Kafka::publish()
                ->onTopic($topic)
                ->withMessage($kafkaMessage)
                ->send();

            // Если отправка прошла без ошибок — фиксируем успех в БД
            $this->publishAnalytics($outboxMessage);

            $outboxMessage->update([
                'status' => 'published',
                'published_at' => now(),
                'next_retry_at' => null,
                'error_message' => null,
            ]);

            $this->line(" [x] Доставлено: {$outboxMessage->event_type} [ID: {$outboxMessage->id}]");
        } catch (Throwable $e) {
            // Если Kafka недоступна — откладываем повтор, воркер не падает
            $this->error(" [!] Ошибка отправки ID {$outboxMessage->id}: {$e->getMessage()}");
            $this->scheduleRetry($outboxMessage, $e);
        }
    }

    private function publishAnalytics(OutboxMessage $outboxMessage): void
    {
        if (! in_array($outboxMessage->event_type, ['order.created', 'order.paid', 'order.cancelled'], true)) {
            return;
        }

        $payload = is_array($outboxMessage->payload) ? $outboxMessage->payload : [];

        app(AnalyticsEventPublisher::class)->publish($outboxMessage->event_type, [
            'order_id' => $payload['order_id'] ?? $outboxMessage->aggregate_id,
            'user_id' => $payload['user_id'] ?? null,
            'store_id' => $payload['store_id'] ?? null,
            'total_amount' => $payload['total_amount'] ?? 0,
            'occurred_at' => now()->toIso8601String(),
        ]);
    }

    private function scheduleRetry(OutboxMessage $outboxMessage, Throwable $e): void
    {
        $retryCount = $outboxMessage->retry_count + 1;

        if ($retryCount >= self::MAX_RETRIES) {
            $outboxMessage->update([
                'status' => 'failed',
                'retry_count' => $retryCount,
                'next_retry_at' => null,
                'error_message' => $e->getMessage(),
            ]);

            $this->error(" [!] Сообщение {$outboxMessage->id} исчерпало попытки");

            return;
        }

        $delaySeconds = 2 ** $retryCount;

        $outboxMessage->update([
            'status' => 'pending',
            'retry_count' => $retryCount,
            'next_retry_at' => now()->addSeconds($delaySeconds),
            'error_message' => $e->getMessage(),
        ]);

        $this->line(" [~] Повтор {$retryCount} для ID {$outboxMessage->id} через {$delaySeconds} с");
    }
}
