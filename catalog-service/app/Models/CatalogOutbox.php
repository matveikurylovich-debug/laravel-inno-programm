<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogOutbox extends Model
{
    public $timestamps = false;

    protected $table = 'catalog_outbox';

    protected $fillable = [
        'topic',
        'message_key',
        'event',
        'payload',
        'created_at',
        'published_at',
        'attempts',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
        'published_at' => 'datetime',
    ];
}
