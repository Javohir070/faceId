<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_employee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->timestamp('synced_at')->nullable();
            $table->string('sync_error')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_employee');
    }
};
