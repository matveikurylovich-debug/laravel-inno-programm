<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('outbox_messages', 'retry_count')) {
            return;
        }

        Schema::table('outbox_messages', function (Blueprint $table) {
            $table->unsignedInteger('retry_count')->default(0)->after('status');
            $table->timestamp('next_retry_at')->nullable()->after('retry_count');

            $table->index(['status', 'next_retry_at']);
        });
    }

    public function down(): void
    {
        Schema::table('outbox_messages', function (Blueprint $table) {
            $table->dropIndex(['status', 'next_retry_at']);
            $table->dropColumn(['retry_count', 'next_retry_at']);
        });
    }
};
