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
        Schema::create('store_locations', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name');
            $table->string('city')->default('Минск');
            $table->string('address');
            $table->string('phone')->nullable();
            $table->string('working_hours')->default('10:00 - 22:00');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['store_id', 'is_active']);
        });

        Schema::table('stocks', static function (Blueprint $table): void {
            $table->foreignId('location_id')->nullable()->after('store_id')->constrained('store_locations')->cascadeOnDelete();
        });

        Schema::table('stock_reservations', static function (Blueprint $table): void {
            $table->foreignId('location_id')->nullable()->after('store_id')->constrained('store_locations')->nullOnDelete();
        });

        $now = now();
        $stores = DB::table('stores')->get();

        foreach ($stores as $store) {
            $locationId = DB::table('store_locations')
                ->where('store_id', $store->id)
                ->orderBy('id')
                ->value('id');

            $locationId ??= DB::table('store_locations')->insertGetId([
                'store_id' => $store->id,
                'name' => $store->name,
                'city' => $store->city ?? 'Минск',
                'address' => $store->address !== null && $store->address !== '' ? $store->address : 'Адрес не указан',
                'phone' => $store->phone,
                'working_hours' => $store->working_hours ?? '10:00 - 22:00',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('stocks')
                ->where('store_id', $store->id)
                ->whereNull('location_id')
                ->update(['location_id' => $locationId]);

            DB::table('stock_reservations')
                ->where('store_id', $store->id)
                ->whereNull('location_id')
                ->update(['location_id' => $locationId]);
        }

        Schema::table('stocks', static function (Blueprint $table): void {
            $table->dropUnique(['store_id', 'product_id']);
        });

        Schema::table('stocks', static function (Blueprint $table): void {
            $table->unique(['location_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::table('stocks', static function (Blueprint $table): void {
            $table->dropUnique(['location_id', 'product_id']);
            $table->unique(['store_id', 'product_id']);
            $table->dropConstrainedForeignId('location_id');
        });

        Schema::table('stock_reservations', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('location_id');
        });

        Schema::dropIfExists('store_locations');
    }
};
