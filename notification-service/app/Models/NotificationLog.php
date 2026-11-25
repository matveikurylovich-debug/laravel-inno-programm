<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * @property string $_id
 * @property string $event
 * @property string $channel
 * @property string|null $recipient
 * @property string|null $recipient_email
 * @property array<string, mixed> $payload
 * @property string $status
 * @property int $attempts
 * @property bool $is_dlq
 * @property string|null $error_message
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class NotificationLog extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'notification_logs';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'event',
        'channel',
        'recipient',
        'recipient_email',
        'payload',
        'status',
        'attempts',
        'error_message',
        'is_dlq',
        'sent_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'is_dlq' => 'boolean',
        'sent_at' => 'datetime',
    ];
}
