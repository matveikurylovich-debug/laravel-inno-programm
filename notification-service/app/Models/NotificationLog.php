<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;
use Override;

/**
 * @property string|null $_id
 * @property string|null $event_id
 * @property int|string|null $user_id
 * @property string $event
 * @property NotificationChannel $channel
 * @property string|null $recipient
 * @property array<string, mixed>|string|null $payload
 * @property NotificationStatus $status
 * @property int $attempts
 * @property bool $is_dlq
 * @property string|null $error_message
 * @property Carbon|null $sent_at
 * @property int|string|null $replayed_by
 * @property Carbon|null $replayed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class NotificationLog extends Model
{
    #[Override]
    protected $connection = 'mongodb';

    #[Override]
    protected $table = 'notification_logs';

    #[Override]
    protected $fillable = [
        'event_id',
        'user_id',
        'event',
        'channel',
        'recipient',
        'payload',
        'status',
        'attempts',
        'error_message',
        'is_dlq',
        'sent_at',
        'replayed_by',
        'replayed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'status' => NotificationStatus::class,
            'attempts' => 'integer',
            'is_dlq' => 'boolean',
            'sent_at' => 'datetime',
            'replayed_at' => 'datetime',
        ];
    }

    public function markSent(): void
    {
        $this->update([
            'status' => NotificationStatus::Sent,
            'sent_at' => now(),
            'error_message' => null,
            'is_dlq' => false,
        ]);
    }

    public function markFailed(string $reason, bool $dlq = false): void
    {
        $this->update([
            'status' => NotificationStatus::Failed,
            'error_message' => $reason,
            'is_dlq' => $dlq,
        ]);
    }

    public function markReplayed(int|string|null $actorId): void
    {
        $this->update([
            'status' => NotificationStatus::Sent,
            'sent_at' => now(),
            'error_message' => null,
            'is_dlq' => false,
            'replayed_by' => $actorId,
            'replayed_at' => now(),
        ]);
    }
}
