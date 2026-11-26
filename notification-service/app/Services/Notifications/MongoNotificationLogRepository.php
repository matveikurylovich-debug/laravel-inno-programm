<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Contracts\NotificationLogRepository;
use App\Data\NotificationFilterData;
use App\Enums\NotificationStatus;
use App\Models\NotificationLog;
use App\Support\SensitivePayload;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use JsonException;
use MongoDB\Collection;
use MongoDB\Laravel\Eloquent\Builder as MongoEloquentBuilder;
use MongoDB\UpdateResult;

final class MongoNotificationLogRepository implements NotificationLogRepository
{
    public function findByEventId(string $eventId): ?NotificationLog
    {
        return NotificationLog::query()
            ->where('event_id', $eventId)
            ->first();
    }

    public function findOrFail(string $id): NotificationLog
    {
        return NotificationLog::query()->findOrFail($id);
    }

    /**
     * @return LengthAwarePaginator<int, NotificationLog>
     */
    public function paginate(NotificationFilterData $filters): LengthAwarePaginator
    {
        return NotificationLog::query()
            ->when(
                $filters->status instanceof NotificationStatus,
                fn (Builder $query): Builder => $query->where('status', $filters->status?->value),
            )
            ->when(
                $filters->channel !== null,
                fn (Builder $query): Builder => $query->where('channel', $filters->channel),
            )
            ->when(
                $filters->recipient !== null,
                fn (Builder $query): Builder => $query->where('recipient', 'like', '%'.$filters->recipient.'%'),
            )
            ->when(
                $filters->dateFrom instanceof CarbonInterface,
                fn (Builder $query): Builder => $query->where('created_at', '>=', $filters->dateFrom),
            )
            ->when(
                $filters->dateTo instanceof CarbonInterface,
                fn (Builder $query): Builder => $query->where('created_at', '<=', $filters->dateTo),
            )->latest()
            ->paginate($filters->perPage);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createPending(array $attributes): NotificationLog
    {
        return NotificationLog::query()->create(array_merge([
            'status' => NotificationStatus::Pending,
            'attempts' => 0,
            'is_dlq' => false,
            'error_message' => null,
            'sent_at' => null,
        ], $attributes));
    }

    public function incrementAttempts(NotificationLog $log): NotificationLog
    {
        $log->increment('attempts');

        return $log->fresh() ?? $log;
    }

    public function markSent(NotificationLog $log): NotificationLog
    {
        $log->markSent();

        return $log->fresh() ?? $log;
    }

    public function markFailed(NotificationLog $log, string $reason, bool $dlq = false): NotificationLog
    {
        $log->markFailed($reason, $dlq);

        return $log->fresh() ?? $log;
    }

    public function markReplayed(NotificationLog $log, int|string|null $actorId): NotificationLog
    {
        $log->markReplayed($actorId);

        return $log->fresh() ?? $log;
    }

    /**
     * @return array{unset: int, rewritten: int}
     */
    public function purgeSecrets(): array
    {
        /** @var MongoEloquentBuilder<NotificationLog> $query */
        $query = NotificationLog::query();
        $result = $query->raw(fn (Collection $collection): UpdateResult => $collection->updateMany(
            [],
            ['$unset' => [
                'payload.data.code' => '',
                'payload.data.reset_url' => '',
                'payload.code' => '',
                'payload.reset_url' => '',
            ]],
        ));

        $unset = $result instanceof UpdateResult ? $result->getModifiedCount() : 0;
        $rewritten = 0;

        foreach (NotificationLog::query()->cursor() as $log) {
            $raw = $log->getAttributes()['payload'] ?? null;
            if (! is_string($raw)) {
                continue;
            }

            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                Log::warning('notification.payload_decode_failed', [
                    'notification_id' => (string) $log->getKey(),
                    'exception' => $e,
                ]);

                continue;
            }

            if (! is_array($decoded)) {
                continue;
            }

            /** @var array<string, mixed> $decoded */
            $log->payload = SensitivePayload::redact($decoded);
            $log->save();
            $rewritten++;
        }

        return ['unset' => $unset, 'rewritten' => $rewritten];
    }

    /**
     * @return array{converted: int, failed: int}
     */
    public function convertStringPayloads(): array
    {
        $converted = 0;
        $failed = 0;

        foreach (NotificationLog::query()->cursor() as $log) {
            $raw = $log->getAttributes()['payload'] ?? null;
            if (! is_string($raw)) {
                continue;
            }

            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                $failed++;
                Log::warning('notification.payload_conversion_failed', [
                    'notification_id' => (string) $log->getKey(),
                    'exception' => $e,
                ]);

                continue;
            }

            if (! is_array($decoded)) {
                $failed++;

                continue;
            }

            /** @var array<string, mixed> $decoded */
            $log->payload = SensitivePayload::redact($decoded);
            $log->save();
            $converted++;
        }

        return ['converted' => $converted, 'failed' => $failed];
    }

    public function pruneOlderThan(CarbonInterface $cutoff): int
    {
        $deleted = NotificationLog::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        return is_int($deleted) ? $deleted : 0;
    }
}
