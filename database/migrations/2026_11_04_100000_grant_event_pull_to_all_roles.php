<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Event tortish alohida menyu va hamma foydalanuvchiga ko‘rinadi: ruxsat barcha rollarga beriladi. Foydalanuvchi
 * baribir faqat o‘ziga biriktirilgan qurilmalardan tortadi. Nom atayin config dan olinmagan: config keyin o‘zgarsa
 * ham migratsiya buzilmaydi.
 */
return new class extends Migration
{
    private const PERMISSION = 'Event tortish';

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::query()->firstOrCreate(['name' => self::PERMISSION, 'guard_name' => 'web']);

        Role::query()->where('guard_name', 'web')->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Boshqa rolda oldin bor edimi, bilib bo‘lmaydi: faqat `user` dan olinadi.
     */
    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::query()->where('name', 'user')->where('guard_name', 'web')->first()
            ?->permissions()->detach(
                Permission::query()->where('guard_name', 'web')->where('name', self::PERMISSION)->pluck('id'),
            );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
