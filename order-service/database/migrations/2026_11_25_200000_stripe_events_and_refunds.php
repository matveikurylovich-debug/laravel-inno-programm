<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_events', function (Blueprint $table): void {
            $table->string('event_id')->primary();
            $table->string('type');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->string('stripe_refund_id')->nullable()->after('stripe_payment_intent_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_status_check');
            DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ('pending', 'succeeded', 'failed', 'refunded', 'refund_pending'))");
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('stripe_refund_id');
        });
        Schema::dropIfExists('stripe_events');
    }
};
