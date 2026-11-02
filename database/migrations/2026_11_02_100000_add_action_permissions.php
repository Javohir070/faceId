<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Qo‘shish, tahrirlash va o‘chirish alohida ruxsat bo‘ldi. Hech kim imkoniyatini yo‘qotmasligi uchun yangi ruxsatlar
 * shu bo‘limning ko‘rish ruxsati bor rollar va foydalanuvchilarga beriladi; keyin Rollar sahifasidan olib tashlanadi.
 * Nomlar atayin config dan olinmagan: config keyin o‘zgarsa ham migratsiya buzilmaydi.
 */
return new class extends Migration
{
    private const ACTIONS = [
        'Kategoriyalar' => ['Kategoriyalarni qo‘shish', 'Kategoriyalarni tahrirlash', 'Kategoriyalarni o‘chirish'],
        'Qurilmalar' => ['Qurilmalarni qo‘shish', 'Qurilmalarni tahrirlash', 'Qurilmalarni o‘chirish'],
        "Bo'limlar" => ["Bo'limlarni qo‘shish", "Bo'limlarni tahrirlash", "Bo'limlarni o‘chirish"],
        'Guruhlar' => ['Guruhlarni qo‘shish', 'Guruhlarni tahrirlash', 'Guruhlarni o‘chirish'],
        'Jadvallar' => ['Jadvallarni qo‘shish', 'Jadvallarni tahrirlash', 'Jadvallarni o‘chirish'],
        'Xodimlar' => ['Xodimlarni qo‘shish', 'Xodimlarni tahrirlash', 'Xodimlarni o‘chirish'],
        'Izohlar' => ['Izohlarni qo‘shish', 'Izohlarni tahrirlash', 'Izohlarni o‘chirish'],
        'Foydalanuvchilar' => ['Foydalanuvchilarni qo‘shish', 'Foydalanuvchilarni tahrirlash', 'Foydalanuvchilarni o‘chirish'],
        'Rollar' => ['Rollarni qo‘shish', 'Rollarni tahrirlash', 'Rollarni o‘chirish'],
        'Ruxsatlar' => ['Ruxsatlarni qo‘shish', 'Ruxsatlarni tahrirlash', 'Ruxsatlarni o‘chirish'],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $superAdmin = Role::query()->where('name', 'super-admin')->where('guard_name', 'web')->first();

        foreach (self::ACTIONS as $viewName => $actionNames) {
            $view = Permission::query()->where('name', $viewName)->where('guard_name', 'web')->first();

            foreach ($actionNames as $actionName) {
                $action = Permission::query()->firstOrCreate(['name' => $actionName, 'guard_name' => 'web']);

                $superAdmin?->givePermissionTo($action);

                if ($view === null) {
                    continue;
                }

                foreach ($view->roles as $role) {
                    $role->givePermissionTo($action);
                }

                foreach ($view->users as $user) {
                    $user->givePermissionTo($action);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', array_merge(...array_values(self::ACTIONS)))
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
