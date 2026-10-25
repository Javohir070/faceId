<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jonli statistikaga qo‘lda qo‘shilgan kirishlar (faqat o‘z kuni uchun).
     */
    public function up(): void
    {
        Schema::create('visit_adjustments', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->unsignedInteger('entered');
            $table->unsignedInteger('male');
            $table->unsignedInteger('female');
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_adjustments');
    }
};
