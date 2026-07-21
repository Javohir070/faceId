<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('schedules')) {
            return;
        }

        Schema::table('schedules', function (Blueprint $table) {
            if (Schema::hasColumn('schedules', 'enter_date')) {
                $table->dropColumn(['enter_date', 'exit_date']);
            }

            if (! Schema::hasColumn('schedules', 'enter_time')) {
                $table->time('enter_time')->nullable();
                $table->time('exit_time')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('schedules')) {
            return;
        }

        Schema::table('schedules', function (Blueprint $table) {
            if (Schema::hasColumn('schedules', 'enter_time')) {
                $table->dropColumn(['enter_time', 'exit_time']);
            }

            if (! Schema::hasColumn('schedules', 'enter_date')) {
                $table->date('enter_date')->nullable();
                $table->date('exit_date')->nullable();
            }
        });
    }
};
