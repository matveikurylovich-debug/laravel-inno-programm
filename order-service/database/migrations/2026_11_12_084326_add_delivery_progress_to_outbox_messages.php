<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outbox_messages', function (Blueprint $table): void {
            $table->timestamp('kafka_published_at')->nullable();
            $table->timestamp('analytics_published_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('outbox_messages', fn (Blueprint $table) => $table->dropColumn(['kafka_published_at', 'analytics_published_at']));
    }
};
