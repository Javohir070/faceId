<?php

namespace Database\Seeders;

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
        $userRole->syncPermissions([
            config('menu_permissions.home'),
        ]);

        $admin = User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
            ],
        );
        $admin->syncRoles(['super-admin']);

        $testUser = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
            ],
        );
        $testUser->syncRoles(['user']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
