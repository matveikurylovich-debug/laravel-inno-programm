<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\NotificationLogRepository;
use App\Data\NotificationFilterData;
use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Models\NotificationLog;
use App\Support\SensitivePayload;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Pagination\Paginator as AbstractPaginator;
use Illuminate\Support\Facades\Date;
use JsonException;
use RuntimeException;

final class InMemoryNotificationLogRepository implements NotificationLogRepository
{
    /** @var array<string, array<string, mixed>> */
    private array $records = [];

    private int $sequence = 1;

    public function clear(): void
    {
        $this->records = [];
        $this->sequence = 1;
    }

    public function count(): int
    {
        return count($this->records);
    }

    /**
     * @return list<NotificationLog>
     */
    public function all(): array
    {
        return array_values(array_map(
            $this->hydrate(...),
            $this->records,
        ));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function seed(array $attributes): NotificationLog
    {
        $id = isset($attributes['_id']) && is_string($attributes['_id']) && $attributes['_id'] !== ''
            ? $attributes['_id']
            : $this->nextId();

        $eventId = $attributes['event_id'] ?? null;
        if (is_string($eventId) && $eventId !== '') {
            foreach ($this->records as $existing) {
                if (($existing['event_id'] ?? null) === $eventId) {
                    throw new RuntimeException(
                        'E11000 duplicate key error collection: notification_logs index: notification_logs_event_id_unique',
                        11000,
                    );
                }
            }
        }

        $channel = $attributes['channel'] ?? NotificationChannel::Email;
        $status = $attributes['status'] ?? NotificationStatus::Pending;
        $now = $this->formatDate($attributes['created_at'] ?? now()) ?? now()->format('Y-m-d H:i:s');

        $record = [
            '_id' => $id,
            'event_id' => is_string($eventId) && $eventId !== '' ? $eventId : null,
            'user_id' => $attributes['user_id'] ?? null,
            'event' => (string) ($attributes['event'] ?? 'user.registered'),
            'channel' => $channel instanceof NotificationChannel ? $channel->value : (string) $channel,
            'recipient' => isset($attributes['recipient']) && is_string($attributes['recipient']) ? $attributes['recipient'] : null,
            'payload' => $attributes['payload'] ?? [],
            'status' => $status instanceof NotificationStatus ? $status->value : (string) $status,
            'attempts' => (int) ($attributes['attempts'] ?? 0),
            'is_dlq' => (bool) ($attributes['is_dlq'] ?? false),
            'error_message' => isset($attributes['error_message']) && is_string($attributes['error_message']) ? $attributes['error_message'] : null,
            'sent_at' => $this->formatDate($attributes['sent_at'] ?? null),
            'replayed_by' => $attributes['replayed_by'] ?? null,
            'replayed_at' => $this->formatDate($attributes['replayed_at'] ?? null),
            'created_at' => $now,
            'updated_at' => $this->formatDate($attributes['updated_at'] ?? null) ?? $now,
        ];

        $this->records[$id] = $record;

        return $this->hydrate($record);
    }

    public function findByEventId(string $eventId): ?NotificationLog
    {
        foreach ($this->records as $record) {
            if (($record['event_id'] ?? null) === $eventId) {
                return $this->hydrate($record);
            }
        }

        return null;
    }

    public function findOrFail(string $id): NotificationLog
    {
        if (! isset($this->records[$id])) {
            throw (new ModelNotFoundException)->setModel(NotificationLog::class, [$id]);
        }

        return $this->hydrate($this->records[$id]);
    }

    /**
     * @return LengthAwarePaginator<int, NotificationLog>
     */
    public function paginate(NotificationFilterData $filters): LengthAwarePaginator
    {
        $filtered = array_filter($this->records, function (array $record) use ($filters): bool {
            if ($filters->status instanceof NotificationStatus && $record['status'] !== $filters->status->value) {
                return false;
            }

            if ($filters->channel !== null && $record['channel'] !== $filters->channel) {
                return false;
            }

            if ($filters->recipient !== null) {
                $recipient = (string) ($record['recipient'] ?? '');
                if (stripos($recipient, $filters->recipient) === false) {
                    return false;
                }
            }

            $createdAt = Date::parse((string) $record['created_at']);

            if ($filters->dateFrom instanceof CarbonInterface && $createdAt->lt($filters->dateFrom)) {
                return false;
            }

            return ! $filters->dateTo instanceof CarbonInterface || ! $createdAt->gt($filters->dateTo);
        });

        usort(
            $filtered,
            static fn (array $a, array $b): int => strcmp((string) $b['created_at'], (string) $a['created_at']),
        );

        $total = count($filtered);
        $page = max(1, AbstractPaginator::resolveCurrentPage());
        $slice = array_slice($filtered, ($page - 1) * $filters->perPage, $filters->perPage);
        $items = array_map(
            $this->hydrate(...),
            $slice,
        );

        return new Paginator($items, $total, $filters->perPage, $page, [
            'path' => AbstractPaginator::resolveCurrentPath(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createPending(array $attributes): NotificationLog
    {
        return $this->seed(array_merge([
            'status' => NotificationStatus::Pending,
            'attempts' => 0,
            'is_dlq' => false,
            'error_message' => null,
            'sent_at' => null,
        ], $attributes));
    }

    public function incrementAttempts(NotificationLog $log): NotificationLog
    {
        $id = (string) $log->getKey();
        $this->records[$id]['attempts'] = ((int) ($this->records[$id]['attempts'] ?? 0)) + 1;
        $this->records[$id]['updated_at'] = now()->format('Y-m-d H:i:s');

        return $this->hydrate($this->records[$id]);
    }

    public function markSent(NotificationLog $log): NotificationLog
    {
        $id = (string) $log->getKey();
        $now = now()->format('Y-m-d H:i:s');
        $this->records[$id]['status'] = NotificationStatus::Sent->value;
        $this->records[$id]['sent_at'] = $now;
        $this->records[$id]['error_message'] = null;
        $this->records[$id]['is_dlq'] = false;
        $this->records[$id]['updated_at'] = $now;

        return $this->hydrate($this->records[$id]);
    }

    public function markFailed(NotificationLog $log, string $reason, bool $dlq = false): NotificationLog
    {
        $id = (string) $log->getKey();
        $this->records[$id]['status'] = NotificationStatus::Failed->value;
        $this->records[$id]['error_message'] = $reason;
        $this->records[$id]['is_dlq'] = $dlq;
        $this->records[$id]['updated_at'] = now()->format('Y-m-d H:i:s');

        return $this->hydrate($this->records[$id]);
    }

    public function markReplayed(NotificationLog $log, int|string|null $actorId): NotificationLog
    {
        $id = (string) $log->getKey();
        $now = now()->format('Y-m-d H:i:s');
        $this->records[$id]['status'] = NotificationStatus::Sent->value;
        $this->records[$id]['sent_at'] = $now;
        $this->records[$id]['error_message'] = null;
        $this->records[$id]['is_dlq'] = false;
        $this->records[$id]['replayed_by'] = $actorId;
        $this->records[$id]['replayed_at'] = $now;
        $this->records[$id]['updated_at'] = $now;

        return $this->hydrate($this->records[$id]);
    }

    /**
     * @return array{unset: int, rewritten: int}
     */
    public function purgeSecrets(): array
    {
        $unset = 0;
        $rewritten = 0;

        foreach ($this->records as $id => $record) {
            $payload = $record['payload'] ?? null;

            if (is_array($payload)) {
                /** @var array<string, mixed> $payload */
                $redacted = SensitivePayload::redact($payload);
                if ($redacted !== $payload) {
                    $this->records[$id]['payload'] = $redacted;
                    $unset++;
                }

                continue;
            }

            if (is_string($payload)) {
                try {
                    $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    continue;
                }

                if (is_array($decoded)) {
                    /** @var array<string, mixed> $decoded */
                    $this->records[$id]['payload'] = SensitivePayload::redact($decoded);
                    $rewritten++;
                }
            }
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

        foreach ($this->records as $id => $record) {
            $payload = $record['payload'] ?? null;
            if (! is_string($payload)) {
                continue;
            }

            try {
                $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $failed++;

                continue;
            }

            if (! is_array($decoded)) {
                $failed++;

                continue;
            }

            /** @var array<string, mixed> $decoded */
            $this->records[$id]['payload'] = SensitivePayload::redact($decoded);
            $converted++;
        }

        return ['converted' => $converted, 'failed' => $failed];
    }

    public function pruneOlderThan(CarbonInterface $cutoff): int
    {
        $deleted = 0;

        foreach ($this->records as $id => $record) {
            if (Date::parse((string) $record['created_at'])->lt($cutoff)) {
                unset($this->records[$id]);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function hydrate(array $raw): NotificationLog
    {
        $model = new NotificationLog;
        $model->setRawAttributes($raw, true);
        $model->exists = true;

        return $model;
    }

    private function formatDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return Date::instance($value)->format('Y-m-d H:i:s');
        }

        if (is_string($value) && $value !== '') {
            return Date::parse($value)->format('Y-m-d H:i:s');
        }

        return null;
    }

    private function nextId(): string
    {
        return sprintf('65f0000000000000%08x', $this->sequence++);
    }
}
