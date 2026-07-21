<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('access_controller_event_id')->nullable()
                ->constrained('access_controller_events')->nullOnDelete();

            $table->string('employee_name');
            $table->string('department_name')->nullable();
            $table->string('device_name')->nullable();
            $table->string('employee_no')->nullable();
            $table->string('card_no')->nullable();
            $table->string('type', 16);
            $table->timestamp('occurred_at');
            $table->string('verify_mode')->nullable();
            $table->string('picture')->nullable();

            $table->timestamps();

            $table->index(['occurred_at']);
            $table->index(['employee_id', 'occurred_at']);
            $table->index(['department_id', 'occurred_at']);
            $table->index(['device_id', 'occurred_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
