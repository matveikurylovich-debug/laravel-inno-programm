<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stocks', function (Blueprint $table): void {
            $table->dropForeign(['location_id']);
        });

        Schema::table('stocks', function (Blueprint $table): void {
            $table->unsignedBigInteger('location_id')->nullable(false)->change();
            $table->foreign('location_id')->references('id')->on('store_locations')->restrictOnDelete();
        });

        Schema::table('stock_reservations', function (Blueprint $table): void {
            $table->dropForeign(['location_id']);
            $table->dropForeign(['product_id']);
        });

        Schema::table('stock_reservations', function (Blueprint $table): void {
            $table->unsignedBigInteger('location_id')->nullable(false)->change();
            $table->foreign('location_id')->references('id')->on('store_locations')->restrictOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table): void {
            $table->dropForeign(['location_id']);
        });

        Schema::table('stocks', function (Blueprint $table): void {
            $table->unsignedBigInteger('location_id')->nullable()->change();
            $table->foreign('location_id')->references('id')->on('store_locations')->cascadeOnDelete();
        });

        Schema::table('stock_reservations', function (Blueprint $table): void {
            $table->dropForeign(['location_id']);
            $table->dropForeign(['product_id']);
        });

        Schema::table('stock_reservations', function (Blueprint $table): void {
            $table->unsignedBigInteger('location_id')->nullable()->change();
            $table->foreign('location_id')->references('id')->on('store_locations')->nullOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }
};
