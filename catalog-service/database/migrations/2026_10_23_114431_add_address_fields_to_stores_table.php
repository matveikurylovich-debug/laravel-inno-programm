<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', static function (Blueprint $table): void {
            $table->string('city')->default('Минск');
            $table->string('address')->default('');
            $table->string('phone')->nullable();
            $table->string('working_hours')->default('10:00 - 22:00');
        });
    }

    public function down(): void
    {
        Schema::table('stores', static function (Blueprint $table): void {
            $table->dropColumn(['city', 'address', 'phone', 'working_hours']);
        });
    }
};
