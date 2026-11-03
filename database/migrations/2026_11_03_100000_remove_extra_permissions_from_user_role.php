<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * `user` roliga Ruxsatlar, Kategoriyalar, Xodim tortish va Event tortish kerak emas: rolga qo‘lda berilgan
 * bo‘lsa ham olib tashlanadi. Boshqa rollarga tegilmaydi. Nomlar atayin config dan olinmagan: config keyin
 * o‘zgarsa ham migratsiya buzilmaydi.
 */
return new class extends Migration
{
    private const REMOVED = [
        'Ruxsatlar', 'Ruxsatlarni qo‘shish', 'Ruxsatlarni tahrirlash', 'Ruxsatlarni o‘chirish',
        'Kategoriyalar', 'Kategoriyalarni qo‘shish', 'Kategoriyalarni tahrirlash', 'Kategoriyalarni o‘chirish',
        'Xodim tortish',
        'Event tortish',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::query()->where('name', 'user')->where('guard_name', 'web')->first();

        if ($role !== null) {
            $role->permissions()->detach(
                Permission::query()->where('guard_name', 'web')->whereIn('name', self::REMOVED)->pluck('id'),
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Qaysi ruxsat qo‘lda berilganini bilib bo‘lmaydi: faqat oldingi standart (Event tortish) qaytariladi.
     */
    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::query()->where('name', 'user')->where('guard_name', 'web')->first()
            ?->givePermissionTo(Permission::query()->firstOrCreate(['name' => 'Event tortish', 'guard_name' => 'web']));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
