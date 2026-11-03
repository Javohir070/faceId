<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * `user` rolining standart huquqlari: ish bo‘limlarida to‘liq, qurilmalarda faqat ko‘rish. Rolga qo‘lda berilgan
 * boshqa ruxsatlar olib tashlanmaydi. Nomlar atayin config dan olinmagan: config keyin o‘zgarsa ham migratsiya buzilmaydi.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'Bosh sahifa',
        'Xodimlar', 'Xodimlarni qo‘shish', 'Xodimlarni tahrirlash', 'Xodimlarni o‘chirish',
        'Guruhlar', 'Guruhlarni qo‘shish', 'Guruhlarni tahrirlash', 'Guruhlarni o‘chirish',
        'Hisobotlar',
        'Izohlar', 'Izohlarni qo‘shish', 'Izohlarni tahrirlash', 'Izohlarni o‘chirish',
        'Jadvallar', 'Jadvallarni qo‘shish', 'Jadvallarni tahrirlash', 'Jadvallarni o‘chirish',
        'Sinxronizatsiya',
        'Event tortish',
        'Davomat',
        'Qurilmalar',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::query()->where('name', 'user')->where('guard_name', 'web')->first();

        if ($role !== null) {
            foreach (self::PERMISSIONS as $name) {
                $role->givePermissionTo(Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::query()->where('name', 'user')->where('guard_name', 'web')->first();

        if ($role !== null) {
            foreach (array_diff(self::PERMISSIONS, ['Bosh sahifa']) as $name) {
                $role->revokePermissionTo($name);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
