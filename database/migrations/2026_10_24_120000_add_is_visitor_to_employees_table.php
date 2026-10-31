<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tadbir mehmonlari (masalan, Innoweek ishtirokchilari) — faqat Jonli kuzatuv va Tashrif statistikasida
     * ko‘rinadi; xodimlar, davomat, hisobotlar va sinxronizatsiyaga aralashmaydi.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('is_visitor')->default(false)->after('country')->index();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['is_visitor']);
            $table->dropColumn('is_visitor');
        });
    }
};
