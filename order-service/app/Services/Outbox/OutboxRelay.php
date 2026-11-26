<?php

declare(strict_types=1);

namespace App\Services\Outbox;

use App\Enums\OrderEventType;
use App\Messaging\Producers\AnalyticsEventsProducer;
use App\Messaging\Producers\OrderEventsProducer;
use App\Models\OutboxMessage;
use App\Support\Money;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Throwable;

class OutboxRelay
{
    private bool $running = true;

    public function __construct(
        private readonly OrderEventsProducer $producer,
        private readonly AnalyticsEventsProducer $analytics,
    ) {}

    public function stop(): void
    {
        $this->running = false;
    }

    public function run(int $batchSize, int $sleepSeconds, bool $once): int
    {
        DB::disableQueryLog();
        $processed = 0;

        while ($this->running) {
            $processed += $this->processBatch($batchSize);
            $this->warnIfLagging();

            if ($once || ! $this->running) {
                return $processed;
            }

            if ($processed === 0) {
                Sleep::sleep(max(1, $sleepSeconds));
            }

            $processed = 0;
        }

        return $processed;
    }

    public function processBatch(int $batchSize): int
    {
        $messages = $this->claim($batchSize);

        foreach ($messages as $message) {
            if (! $this->running) {
                break;
            }

            DB::transaction(function () use ($message): void {
                $locked = OutboxMessage::query()->lockForUpdate()->find($message->id);
                if ($locked === null || $locked->status !== 'pending') {
                    return;
                }
                if ($locked->next_retry_at !== null && $locked->next_retry_at > now()) {
                    return;
                }
                if (! $this->payloadIsValid($locked)) {
                    $locked->update([
                        'status' => 'dead',
                        'next_retry_at' => null,
                        'error_message' => 'invalid payload',
                    ]);

                    return;
                }

                $this->publishMessage($locked);
            });
        }

        return $messages->count();
    }

    /**
     * @return Collection<int, OutboxMessage>
     */
    private function claim(int $batchSize)
    {
        $query = OutboxMessage::query()
            ->where('status', 'pending')
            ->where(function (Builder $query): void {
                $query->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            })
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('outbox_messages as earlier')
                    ->whereColumn('earlier.aggregate_id', 'outbox_messages.aggregate_id')
                    ->where('earlier.status', 'pending')
                    ->whereColumn('earlier.id', '<', 'outbox_messages.id');
            })
            ->orderBy('id')
            ->limit($batchSize);

        if (DB::getDriverName() === 'pgsql') {
            $query->lock('FOR UPDATE SKIP LOCKED');
        }

        return $query->get();
    }

    private function publishMessage(OutboxMessage $message): void
    {
        try {
            if ($message->kafka_published_at === null) {
                $this->producer->publish($message);
                $message->update(['kafka_published_at' => now()]);
            }

            if ($message->analytics_published_at === null) {
                $this->publishAnalytics($message);
                $message->update(['analytics_published_at' => now()]);
            }

            $message->update([
                'status' => 'published',
                'published_at' => now(),
                'next_retry_at' => null,
                'error_message' => null,
            ]);
        } catch (Throwable $exception) {
            $this->scheduleRetry($message, $exception);
        }
    }

    private function publishAnalytics(OutboxMessage $message): void
    {
        if (! in_array($message->event_type, [
            OrderEventType::Created->value,
            OrderEventType::Paid->value,
            OrderEventType::Cancelled->value,
            OrderEventType::Refunded->value,
        ], true)) {
            return;
        }

        $payload = $message->payload;
        $body = [
            'order_id' => $payload['order_id'] ?? $message->aggregate_id,
            'user_id' => $payload['user_id'] ?? null,
            'store_id' => $payload['store_id'] ?? null,
            'event_id' => $payload['event_id'] ?? 'order-outbox-'.$message->id,
            'occurred_at' => $payload['occurred_at'] ?? ($message->created_at ?? now())->toIso8601String(),
        ];

        if ($message->event_type === OrderEventType::Refunded->value) {
            $rawRefunded = $payload['refunded_amount'] ?? '0.00';
            $body['refunded_amount'] = is_string($rawRefunded) || is_int($rawRefunded) || is_float($rawRefunded)
                ? Money::decimal($rawRefunded)
                : '0.00';
            if (isset($payload['currency']) && is_string($payload['currency']) && $payload['currency'] !== '') {
                $body['currency'] = $payload['currency'];
            }
        } else {
            $body['total_amount'] = $payload['total_amount'] ?? 0;
            if ($message->event_type === OrderEventType::Cancelled->value) {
                $rawRefunded = $payload['refunded_amount'] ?? '0.00';
                $body['refunded_amount'] = is_string($rawRefunded) || is_int($rawRefunded) || is_float($rawRefunded)
                    ? Money::decimal($rawRefunded)
                    : '0.00';
            }
        }

        $this->analytics->publish($message->event_type, $body, true);
    }

    private function scheduleRetry(OutboxMessage $message, Throwable $exception): void
    {
        $retryCount = $message->retry_count + 1;
        $delaySeconds = min(2 ** $retryCount, 60);

        $message->update([
            'status' => 'pending',
            'retry_count' => $retryCount,
            'next_retry_at' => now()->addSeconds($delaySeconds),
            'error_message' => $exception->getMessage(),
        ]);

        Log::warning('Outbox откладывает повтор', [
            'id' => $message->id,
            'retry' => $retryCount,
            'delay_seconds' => $delaySeconds,
            'exception' => $exception,
        ]);
    }

    private function payloadIsValid(OutboxMessage $message): bool
    {
        $payload = $message->payload;
        $orderId = $payload['order_id'] ?? null;

        if (! is_string($orderId) || $orderId === '') {
            $data = $payload['data'] ?? null;
            $orderId = is_array($data) ? ($data['order_id'] ?? null) : null;
        }

        return is_string($orderId) && $orderId !== '';
    }

    private function warnIfLagging(): void
    {
        $oldest = OutboxMessage::query()->where('status', 'pending')->orderBy('id')->first();
        if ($oldest === null) {
            return;
        }

        $lag = (int) abs(now()->diffInSeconds($oldest->created_at));
        if ($lag > (int) config('orders.outbox_lag_seconds', 120)) {
            Log::warning('Outbox отстаёт', ['seconds' => $lag, 'id' => $oldest->id]);
        }
    }
}
