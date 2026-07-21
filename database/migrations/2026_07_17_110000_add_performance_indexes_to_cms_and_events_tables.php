<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_controller_events', function (Blueprint $table) {
            $table->index('event_time');
            $table->index(['employee_no', 'event_time']);
            $table->index(['card_no', 'event_time']);
            $table->index(['device_ip', 'event_time']);
        });
    }

    public function down(): void
    {
        Schema::table('access_controller_events', function (Blueprint $table) {
            $table->dropIndex(['event_time']);
            $table->dropIndex(['employee_no', 'event_time']);
            $table->dropIndex(['card_no', 'event_time']);
            $table->dropIndex(['device_ip', 'event_time']);
        });
    }
};
