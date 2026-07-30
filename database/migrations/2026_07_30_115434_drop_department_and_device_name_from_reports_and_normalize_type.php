<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reports')) {
            $this->createReportsTable();

            return;
        }

        if ($this->reportsTableIsCorrupt()) {
            Schema::drop('reports');
            $this->createReportsTable();

            return;
        }

        $dropColumns = collect(['department_name', 'device_name'])
            ->filter(fn (string $column): bool => Schema::hasColumn('reports', $column))
            ->values()
            ->all();

        if ($dropColumns !== []) {
            Schema::table('reports', function (Blueprint $table) use ($dropColumns) {
                $table->dropColumn($dropColumns);
            });
        }

        $this->normalizeTypeColumn();
    }

    public function down(): void
    {
        if (! Schema::hasTable('reports') || $this->reportsTableIsCorrupt()) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE reports MODIFY type VARCHAR(16) NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE reports ALTER COLUMN type TYPE VARCHAR(16)');
        }

        DB::table('reports')->where('type', 1)->update(['type' => 'entry']);
        DB::table('reports')->where('type', 2)->update(['type' => 'exit']);

        Schema::table('reports', function (Blueprint $table) {
            if (! Schema::hasColumn('reports', 'department_name')) {
                $table->string('department_name')->nullable()->after('employee_name');
            }

            if (! Schema::hasColumn('reports', 'device_name')) {
                $table->string('device_name')->nullable()->after('department_name');
            }
        });
    }

    private function createReportsTable(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('device_id')->nullable();

            $table->string('employee_name');
            $table->unsignedTinyInteger('type');
            $table->timestamp('occurred_at');

            $table->timestamps();

            $table->index('employee_id', 'reports_employee_id_foreign');
            $table->index('department_id', 'reports_department_id_foreign');
            $table->index('device_id', 'reports_device_id_foreign');
            $table->index(['occurred_at']);
            $table->index(['employee_id', 'occurred_at']);
            $table->index(['department_id', 'occurred_at']);
            $table->index(['device_id', 'occurred_at']);
            $table->index('type');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
            $table->foreign('device_id')->references('id')->on('devices')->nullOnDelete();
        });
    }

    private function normalizeTypeColumn(): void
    {
        if (! Schema::hasColumn('reports', 'type')) {
            return;
        }

        DB::table('reports')->where('type', 'entry')->update(['type' => '1']);
        DB::table('reports')->where('type', 'exit')->update(['type' => '2']);
        DB::table('reports')
            ->whereNotIn('type', ['1', '2'])
            ->update(['type' => '1']);

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE reports MODIFY type TINYINT UNSIGNED NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE reports ALTER COLUMN type TYPE SMALLINT USING type::smallint');
        }
    }

    private function reportsTableIsCorrupt(): bool
    {
        try {
            DB::table('reports')->limit(1)->get();

            return false;
        } catch (\Throwable) {
            return true;
        }
    }
};
