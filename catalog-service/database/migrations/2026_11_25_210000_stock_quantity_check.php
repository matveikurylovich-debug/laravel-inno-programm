<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('stocks')->where('quantity', '<', 0)->update(['quantity' => 0]);
        DB::table('stocks')->where('reserved_quantity', '<', 0)->update(['reserved_quantity' => 0]);
        DB::table('stocks')->whereColumn('reserved_quantity', '>', 'quantity')->update([
            'reserved_quantity' => DB::raw('quantity'),
        ]);

        $storeIds = DB::table('stocks')->whereNull('location_id')->distinct()->pluck('store_id')
            ->merge(DB::table('stock_reservations')->whereNull('location_id')->distinct()->pluck('store_id'))
            ->unique();

        foreach ($storeIds as $storeId) {
            $locationId = DB::table('store_locations')->where('store_id', $storeId)->orderBy('id')->value('id');
            $locationId ??= DB::table('store_locations')->insertGetId([
                'store_id' => $storeId,
                'name' => 'Основная',
                'city' => 'Минск',
                'address' => 'Не указан',
                'working_hours' => '10:00 - 22:00',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('stocks')->where('store_id', $storeId)->whereNull('location_id')->update(['location_id' => $locationId]);
            DB::table('stock_reservations')->where('store_id', $storeId)->whereNull('location_id')->update(['location_id' => $locationId]);
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE stocks ADD CONSTRAINT stocks_quantity_check CHECK (quantity >= 0 AND reserved_quantity >= 0 AND reserved_quantity <= quantity)');
        } else {
            $sql = 'NEW.quantity >= 0 AND NEW.reserved_quantity >= 0 AND NEW.reserved_quantity <= NEW.quantity';
            DB::unprepared(<<<SQL
                CREATE TRIGGER stocks_quantity_check_insert
                BEFORE INSERT ON stocks
                WHEN NOT ({$sql})
                BEGIN
                    SELECT RAISE(ABORT, 'stock quantity invariant');
                END
            SQL);
            DB::unprepared(<<<SQL
                CREATE TRIGGER stocks_quantity_check_update
                BEFORE UPDATE ON stocks
                WHEN NOT ({$sql})
                BEGIN
                    SELECT RAISE(ABORT, 'stock quantity invariant');
                END
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE stocks DROP CONSTRAINT IF EXISTS stocks_quantity_check');
        } else {
            DB::unprepared('DROP TRIGGER IF EXISTS stocks_quantity_check_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS stocks_quantity_check_update');
        }
    }
};
