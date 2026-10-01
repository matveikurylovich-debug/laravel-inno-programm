<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

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
        'payload',
        'status',
        'attempts',
        'error_message',
        'is_dlq',
        'sent_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'is_dlq' => 'boolean',
        'sent_at' => 'datetime',
    ];
}
