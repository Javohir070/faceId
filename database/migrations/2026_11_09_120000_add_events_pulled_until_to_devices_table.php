<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Qurilmadan eventlar shu vaqtgacha to‘liq o‘qilgan (hikvision.event_timezone bo‘yicha).
     * Zaxira tortish shu yerdan davom etadi: uzilish paytidagi eventlar o‘tkazib yuborilmaydi.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->dateTime('events_pulled_until')->nullable()->after('last_ping_at');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->dropColumn('events_pulled_until');
        });
    }
};
