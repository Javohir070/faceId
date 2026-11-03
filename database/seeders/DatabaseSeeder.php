<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            config('menu_permissions.home'),
            ...array_values(config('menu_permissions.resources')),
            ...array_merge(...array_map('array_values', array_values(config('menu_permissions.actions')))),
            'Sozlamalar',
        ];

        foreach (array_unique($permissions) as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $allPermissions = Permission::query()->where('guard_name', 'web')->get();

        $superAdminRole = Role::query()->firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => 'web',
        ]);
        $superAdminRole->syncPermissions($allPermissions);

        $userRole = Role::query()->firstOrCreate([
            'name' => 'user',
            'guard_name' => 'web',
        ]);
        $userRole->syncPermissions($this->userRolePermissions());

        $admin = User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
            ],
        );
        $admin->syncRoles(['super-admin']);

        $defaultDepartment = Department::query()->firstOrCreate(
            ['name' => 'Umumiy'],
        );

        $testUser = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'department_id' => $defaultDepartment->id,
            ],
        );
        if ($testUser->department_id === null) {
            $testUser->update(['department_id' => $defaultDepartment->id]);
        }
        $testUser->syncRoles(['user']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return list<string>
     */
    private function userRolePermissions(): array
    {
        $resources = config('menu_permissions.resources');
        $actions = config('menu_permissions.actions');
        $names = [config('menu_permissions.home')];

        foreach (config('menu_permissions.user_role.full') as $key) {
            $names = [...$names, $resources[$key], ...array_values($actions[$key] ?? [])];
        }

        foreach (config('menu_permissions.user_role.view') as $key) {
            $names[] = $resources[$key];
        }

        return $names;
    }
}
