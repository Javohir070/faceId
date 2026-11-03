<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Xodim davlati olib tashlandi. down() faqat ustunni qaytaradi — o‘chirilgan qiymatlar tiklanmaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('country');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->char('country', 2)->default('UZ')->after('gender');
        });
    }
};
