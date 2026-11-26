<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::connection('mongodb')->table('notification_logs', function (Blueprint $collection): void {
            $collection->unique(
                ['event_id' => 1],
                'notification_logs_event_id_unique',
                null,
                [
                    'partialFilterExpression' => [
                        'event_id' => ['$type' => 'string'],
                    ],
                ],
            );

            $collection->index(['status' => 1, 'created_at' => -1], 'notification_logs_status_created_at_idx');
            $collection->index(['channel' => 1, 'created_at' => -1], 'notification_logs_channel_created_at_idx');
            $collection->index(['recipient' => 1, 'created_at' => -1], 'notification_logs_recipient_created_at_idx');
            $collection->index(
                ['created_at' => 1],
                'notification_logs_created_at_ttl',
                null,
                ['expireAfterSeconds' => 90 * 86400],
            );
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->table('notification_logs', function (Blueprint $collection): void {
            $collection->dropIndexIfExists('notification_logs_event_id_unique');
            $collection->dropIndexIfExists('notification_logs_status_created_at_idx');
            $collection->dropIndexIfExists('notification_logs_channel_created_at_idx');
            $collection->dropIndexIfExists('notification_logs_recipient_created_at_idx');
            $collection->dropIndexIfExists('notification_logs_created_at_ttl');
        });
    }
};
