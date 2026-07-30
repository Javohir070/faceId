<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reports')) {
            return;
        }

        if (Schema::hasColumn('reports', 'access_controller_event_id')) {
            Schema::table('reports', function (Blueprint $table) {
                $table->dropForeign(['access_controller_event_id']);
            });

            Schema::table('reports', function (Blueprint $table) {
                $table->dropUnique(['access_controller_event_id']);
            });

            Schema::table('reports', function (Blueprint $table) {
                $table->dropColumn('access_controller_event_id');
            });
        }

        $columns = collect(['employee_no', 'card_no', 'verify_mode', 'picture'])
            ->filter(fn (string $column): bool => Schema::hasColumn('reports', $column))
            ->values()
            ->all();

        if ($columns !== []) {
            Schema::table('reports', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            if (! Schema::hasColumn('reports', 'access_controller_event_id')) {
                $table->foreignId('access_controller_event_id')->nullable()
                    ->constrained('access_controller_events')->nullOnDelete();
                $table->unique('access_controller_event_id');
            }

            if (! Schema::hasColumn('reports', 'employee_no')) {
                $table->string('employee_no')->nullable();
            }

            if (! Schema::hasColumn('reports', 'card_no')) {
                $table->string('card_no')->nullable();
            }

            if (! Schema::hasColumn('reports', 'verify_mode')) {
                $table->string('verify_mode')->nullable();
            }

            if (! Schema::hasColumn('reports', 'picture')) {
                $table->string('picture')->nullable();
            }
        });
    }
};
