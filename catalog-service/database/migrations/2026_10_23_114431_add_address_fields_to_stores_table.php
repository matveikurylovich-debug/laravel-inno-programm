<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (! Schema::hasColumn('stores', 'city')) {
                $table->string('city')->default('Минск');
            }
            if (! Schema::hasColumn('stores', 'address')) {
                $table->string('address')->default('');
            }
            if (! Schema::hasColumn('stores', 'phone')) {
                $table->string('phone')->nullable();
            }
            if (! Schema::hasColumn('stores', 'working_hours')) {
                $table->string('working_hours')->default('10:00 - 22:00');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['city', 'address', 'phone', 'working_hours']);
        });
    }
};
