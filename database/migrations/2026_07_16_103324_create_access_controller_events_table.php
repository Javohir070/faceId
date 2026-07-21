<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('access_controller_events', function (Blueprint $table) {
            $table->id();

            $table->string('employee_no')->nullable();
            $table->string('card_no')->nullable();
            $table->string('full_name')->nullable();

            $table->integer('major_event_type')->nullable();
            $table->integer('sub_event_type')->nullable();

            $table->string('verify_mode')->nullable();
            $table->string('user_type')->nullable();
            $table->string('attendance_status')->nullable();

            $table->timestamp('event_time')->nullable();

            $table->string('device_ip')->nullable();
            $table->string('picture')->nullable();

            $table->json('payload')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_controller_events');
    }
};
