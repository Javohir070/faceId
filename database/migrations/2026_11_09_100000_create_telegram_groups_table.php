<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Botga ulangan Telegram guruhlari. Guruh uni /ulash qilgan foydalanuvchiga bog‘lanadi
     * va faqat shu foydalanuvchi ko‘radigan qurilmalar haqida xabar oladi.
     */
    public function up(): void
    {
        Schema::create('telegram_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('chat_id')->unique();
            $table->string('title')->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_groups');
    }
};
