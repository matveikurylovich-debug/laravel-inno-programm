<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Data\NotificationFilterData;
use App\Models\NotificationLog;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface NotificationLogRepository
{
    public function findByEventId(string $eventId): ?NotificationLog;

    public function findOrFail(string $id): NotificationLog;

    /**
     * @return LengthAwarePaginator<int, NotificationLog>
     */
    public function paginate(NotificationFilterData $filters): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createPending(array $attributes): NotificationLog;

    public function incrementAttempts(NotificationLog $log): NotificationLog;

    public function markSent(NotificationLog $log): NotificationLog;

    public function markFailed(NotificationLog $log, string $reason, bool $dlq = false): NotificationLog;

    public function markReplayed(NotificationLog $log, int|string|null $actorId): NotificationLog;

    /**
     * @return array{unset: int, rewritten: int}
     */
    public function purgeSecrets(): array;

    /**
     * @return array{converted: int, failed: int}
     */
    public function convertStringPayloads(): array;

    public function pruneOlderThan(CarbonInterface $cutoff): int;
}
