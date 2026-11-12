<?php

use App\Services\Employees\EmployeeName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Xodim ismi uch maydonda: Familiya, Ism, Otasining ismi. `name` qoladi va formada shulardan yig‘iladi.
 * Mavjud qatorlarning `name` i EmployeeName::split() bilan bo‘linadi: 1-so‘z familiya, 2-so‘z ism, qolgani
 * otasining ismi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->string('last_name')->nullable()->after('name');
            $table->string('first_name')->nullable()->after('last_name');
            $table->string('middle_name')->nullable()->after('first_name');
        });

        DB::table('employees')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->chunkById(500, function ($employees): void {
                foreach ($employees as $employee) {
                    DB::table('employees')
                        ->where('id', $employee->id)
                        ->update(EmployeeName::split($employee->name));
                }
            });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn(['last_name', 'first_name', 'middle_name']);
        });
    }
};
