<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Noto‘g‘ri/eski qiymatlarni tozalash: faqat 1 (Kirish) yoki 2 (Chiqish).
        DB::table('devices')->whereNotIn('type', [1, 2])->update(['type' => 1]);

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE devices MODIFY type TINYINT UNSIGNED NOT NULL DEFAULT 1');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE devices ALTER COLUMN type SET DEFAULT 1');
        }
        // sqlite: default o‘zgartirish cheklangan — factory/validation yetarli.
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE devices MODIFY type TINYINT UNSIGNED NOT NULL DEFAULT 0');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE devices ALTER COLUMN type SET DEFAULT 0');
        }
    }
};
