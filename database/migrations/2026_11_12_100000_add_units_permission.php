<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * "Tarkibiy bo'linmalar" sahifasi: ruxsat super-admin va `user` rollariga beriladi (boshqa rollarga admin rol
 * matritsasidan beradi). Nom atayin config dan olinmagan: config keyin o‘zgarsa ham migratsiya buzilmaydi.
 */
return new class extends Migration
{
    private const PERMISSION = "Tarkibiy bo'linmalar";

    private const ROLES = ['super-admin', 'user'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::query()->firstOrCreate(['name' => self::PERMISSION, 'guard_name' => 'web']);

        Role::query()->where('guard_name', 'web')->whereIn('name', self::ROLES)->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::query()->where('guard_name', 'web')->where('name', self::PERMISSION)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
