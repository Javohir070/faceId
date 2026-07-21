<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->boolean('is_online')->default(false)->after('autoconnect');
            $table->timestamp('last_connected_at')->nullable()->after('is_online');
            $table->boolean('webhook_configured')->default(false)->after('last_connected_at');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['is_online', 'last_connected_at', 'webhook_configured']);
        });
    }
};
