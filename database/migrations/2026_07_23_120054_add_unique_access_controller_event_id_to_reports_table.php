<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Eski o‘rnatishlar: access_controller_event_id hali reports da bo‘lsa — unique qo‘yamiz.
        // Yangi o‘rnatishlarda ustun yo‘q (keyinroq drop migratsiyasi bilan tozalanadi).
        if (! Schema::hasColumn('reports', 'access_controller_event_id')) {
            return;
        }

        $duplicateEventIds = DB::table('reports')
            ->select('access_controller_event_id')
            ->whereNotNull('access_controller_event_id')
            ->groupBy('access_controller_event_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('access_controller_event_id');

        foreach ($duplicateEventIds as $eventId) {
            $keepId = DB::table('reports')
                ->where('access_controller_event_id', $eventId)
                ->orderBy('id')
                ->value('id');

            DB::table('reports')
                ->where('access_controller_event_id', $eventId)
                ->where('id', '!=', $keepId)
                ->delete();
        }

        Schema::table('reports', function (Blueprint $table) {
            $table->unique('access_controller_event_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('reports', 'access_controller_event_id')) {
            return;
        }

        Schema::table('reports', function (Blueprint $table) {
            $table->dropUnique(['access_controller_event_id']);
        });
    }
};
