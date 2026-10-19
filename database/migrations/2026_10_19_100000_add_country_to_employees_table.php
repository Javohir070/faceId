<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mahalliy/xorijiy statistikasi uchun davlat (ISO 3166-1 alpha-2). Mavjud va qurilmadan
     * import qilinadigan xodimlar O‘zbekiston bo‘lib qoladi.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->char('country', 2)->default('UZ')->after('gender');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('country');
        });
    }
};
