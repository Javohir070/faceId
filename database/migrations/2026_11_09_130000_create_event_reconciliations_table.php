<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Har kecha qurilmadagi va tizimdagi kirish-chiqishlar solishtiriladi (qurilma × kun).
     * Sonlar faqat bizning xodimlar bo‘yicha; begona — terminalda bor, tizimda yo‘q odamlar soni.
     */
    public function up(): void
    {
        Schema::create('event_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('device_count')->nullable();
            $table->unsignedInteger('system_count')->nullable();
            $table->unsignedInteger('recovered')->default(0);
            $table->unsignedInteger('strangers')->nullable();
            $table->string('status', 20);
            $table->string('error')->nullable();
            $table->dateTime('checked_at');
            $table->timestamps();

            $table->unique(['device_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_reconciliations');
    }
};
