<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tadbir (Innoweek) funksiyalari olib tashlandi: Jonli kuzatuv, Tashrif statistikasi, qo‘lda statistika,
 * kirganlar ro‘yxati, zal ekranlari, mehmonlar importi va turniket. Bu migratsiya ularning ma’lumotini tozalaydi:
 * mehmon xodimlar (is_visitor) bilan ularning o‘tishlari va qurilma hodisalari, visit_adjustments jadvali va huquqlar.
 * down() faqat tuzilmani qaytaradi — o‘chirilgan ma’lumot tiklanmaydi.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['Jonli kuzatuv', 'Statistika qo‘shish'];

    public function up(): void
    {
        if (Schema::hasColumn('employees', 'is_visitor')) {
            DB::table('employees')
                ->where('is_visitor', true)
                ->select(['id', 'card'])
                ->chunkById(500, function (Collection $visitors): void {
                    $cards = $visitors->pluck('card')->filter()->values()->all();
                    if ($cards !== []) {
                        DB::table('access_controller_events')->whereIn('employee_no', $cards)->delete();
                    }

                    $ids = $visitors->pluck('id')->all();
                    // reports.employee_id nullOnDelete — o‘tishlar egasiz qolmasligi uchun avval o‘chiriladi.
                    DB::table('reports')->whereIn('employee_id', $ids)->delete();
                    // faces, device_employee, notes — FK cascadeOnDelete.
                    DB::table('employees')->whereIn('id', $ids)->delete();
                });

            Schema::table('employees', function (Blueprint $table) {
                $table->dropIndex(['is_visitor']);
                $table->dropColumn('is_visitor');
            });
        }

        Schema::dropIfExists('visit_adjustments');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::query()->whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! Schema::hasColumn('employees', 'is_visitor')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->boolean('is_visitor')->default(false)->after('country')->index();
            });
        }

        if (! Schema::hasTable('visit_adjustments')) {
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

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $superAdmin = Role::query()->where('name', 'super-admin')->first();
        foreach (self::PERMISSIONS as $name) {
            $permission = Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $superAdmin?->givePermissionTo($permission);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
