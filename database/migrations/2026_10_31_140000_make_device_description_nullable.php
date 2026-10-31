<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Qurilma "Tavsif" maydoni interfeysdan olib tashlandi. Ustun o‘chirilmaydi (eski tavsiflar saqlanadi),
 * faqat ixtiyoriy qilinadi — yangi qurilmalar tavsifsiz yoziladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('description')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('devices')->whereNull('description')->update(['description' => '']);

        Schema::table('devices', function (Blueprint $table) {
            $table->string('description')->nullable(false)->change();
        });
    }
};
