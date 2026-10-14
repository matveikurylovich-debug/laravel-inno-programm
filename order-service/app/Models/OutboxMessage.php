<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboxMessage extends Model
{
    protected $fillable = [
        'event_type',
        'aggregate_type',
        'aggregate_id',
        'payload',
        'status',
        'retry_count',
        'next_retry_at',
        'published_at',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'retry_count' => 'integer',
        'next_retry_at' => 'datetime',
        'published_at' => 'datetime',
    ];
}
