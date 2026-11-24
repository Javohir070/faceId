<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kechikishga ruxsat (daqiqa): kirish vaqtidan shuncha daqiqa ichida kelgan xodim kechikkan hisoblanmaydi.
 * Mavjud jadvallar 0 oladi — hozirgi hisobotlar o‘zgarmaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table): void {
            $table->unsignedSmallInteger('late_grace_minutes')->default(0)->after('exit_time');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table): void {
            $table->dropColumn('late_grace_minutes');
        });
    }
};
