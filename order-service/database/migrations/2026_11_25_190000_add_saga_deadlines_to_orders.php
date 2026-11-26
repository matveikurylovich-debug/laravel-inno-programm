<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('payment_expires_at')->nullable()->after('cancel_reason');
            $table->timestamp('reserve_deadline')->nullable()->after('payment_expires_at');
            $table->timestamp('reserve_republished_at')->nullable()->after('reserve_deadline');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['payment_expires_at', 'reserve_deadline', 'reserve_republished_at']);
        });
    }
};
