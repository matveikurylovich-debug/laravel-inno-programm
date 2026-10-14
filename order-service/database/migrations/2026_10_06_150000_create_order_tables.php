<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('store_id');
            $table->string('status')->default('created');
            $table->string('payment_status')->default('pending');
            $table->decimal('total_amount', 12, 2);
            $table->string('currency', 3)->default('usd');
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->string('product_name');
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });

        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->string('aggregate_type');
            $table->uuid('aggregate_id');
            $table->jsonb('payload');
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
