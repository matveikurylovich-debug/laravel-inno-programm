<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string, mixed> $payload
 */
#[Fillable([
    'topic',
    'message_key',
    'event',
    'payload',
    'created_at',
    'published_at',
    'attempts',
])]
#[Table(name: 'catalog_outbox')]
#[WithoutTimestamps]
class CatalogOutbox extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }
}
