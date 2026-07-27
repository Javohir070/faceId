<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Eski: 0 = Kirish, 1 = Chiqish.
     * Yangi: 1 = Kirish, 2 = Chiqish.
     */
    public function up(): void
    {
        // Avval eski Chiqish (1) → 2, keyin eski Kirish (0) → 1.
        DB::table('devices')->where('type', 1)->update(['type' => 2]);
        DB::table('devices')->where('type', 0)->update(['type' => 1]);
    }

    public function down(): void
    {
        DB::table('devices')->where('type', 1)->update(['type' => 0]);
        DB::table('devices')->where('type', 2)->update(['type' => 1]);
    }
};
