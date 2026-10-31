<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qurilma ↔ foydalanuvchi bog‘lanishi: super-admin bo‘lmagan foydalanuvchi faqat o‘zi qo‘shgan
 * (devices.user_id) va shu jadval orqali o‘ziga bog‘langan qurilmalarni ko‘radi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['device_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_user');
    }
};
