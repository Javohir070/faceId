<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Parking API (docs/parking-api.md) ga kirish ruxsati. Faqat super-admin ga beriladi: parking tizimi uchun rol va
 * foydalanuvchini administrator o‘zi yaratadi. Nom atayin config dan olinmagan: config keyin o‘zgarsa ham
 * migratsiya buzilmaydi.
 */
return new class extends Migration
{
    private const PERMISSION = 'Parking API';

    private const ROLES = ['super-admin'];

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
