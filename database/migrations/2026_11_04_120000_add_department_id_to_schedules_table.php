<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bo‘sh department_id — umumiy jadval: hamma ko‘radi, faqat super-admin o‘zgartiradi.
     * Mavjud jadvallar shunday qoladi. Bo‘lim o‘chirilsa uning jadvallari umumiy bo‘lib qoladi.
     */
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table): void {
            $table->foreignId('department_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
