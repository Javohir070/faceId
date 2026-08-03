<?php

namespace Tests\Concerns;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

trait InteractsWithPermissions
{
    /**
     * @param  list<string>|null  $permissions
     */
    protected function createUserWithPermissions(?array $permissions = null): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $names = $permissions ?? $this->allMenuPermissions();

        foreach ($names as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $user = User::factory()->create();
        $user->givePermissionTo($names);

        // Bypass department scope in existing admin tests (full visibility).
        Role::findOrCreate('super-admin', 'web');
        $user->assignRole('super-admin');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    /**
     * @param  list<string>|null  $permissions
     */
    protected function createDepartmentUserWithPermissions(int $departmentId, ?array $permissions = null): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $names = $permissions ?? $this->allMenuPermissions();

        foreach ($names as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Role::findOrCreate('user', 'web');

        $user = User::factory()->create(['department_id' => $departmentId]);
        $user->givePermissionTo($names);
        $user->assignRole('user');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    /**
     * @return list<string>
     */
    protected function allMenuPermissions(): array
    {
        return array_values(array_unique([
            config('menu_permissions.home'),
            ...array_values(config('menu_permissions.resources')),
            'Sozlamalar',
        ]));
    }
}
