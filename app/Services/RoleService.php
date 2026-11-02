<?php

namespace App\Services;

use App\Interfaces\PermissionRepositoryInterface;
use App\Interfaces\RoleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleService
{
    public function __construct(
        private readonly RoleRepositoryInterface $roleRepository,
        private readonly PermissionRepositoryInterface $permissionRepository,
    ) {}

    public function paginate(int $perPage = 12): LengthAwarePaginator
    {
        return $this->roleRepository->paginate($perPage);
    }

    /**
     * Rol formasidagi jadval: har bir ko‘rish ruxsati qatori va uning qo‘shish/tahrirlash/o‘chirish ruxsati nomi.
     * Amal ruxsatlari alohida qator bo‘lib chiqmaydi.
     *
     * @return array<string, array<int, array{id: int, name: string, create: ?string, edit: ?string, delete: ?string}>>
     */
    public function permissionMatrix(): array
    {
        $actions = collect(config('menu_permissions.actions'))
            ->mapWithKeys(fn (array $names, string $resource): array => [config("menu_permissions.resources.{$resource}") => $names]);
        $existing = Permission::query()->where('guard_name', 'web')->pluck('name')->flip();
        $actionFor = fn (string $view, string $action): ?string => isset($actions[$view][$action], $existing[$actions[$view][$action]])
            ? $actions[$view][$action]
            : null;

        return collect($this->groupedPermissions())
            ->map(fn (Collection $group): array => $group
                ->values()
                ->map(fn (Permission $permission): array => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'create' => $actionFor($permission->name, 'create'),
                    'edit' => $actionFor($permission->name, 'edit'),
                    'delete' => $actionFor($permission->name, 'delete'),
                ])
                ->all())
            ->all();
    }

    /**
     * @return array<string, Collection<int, Permission>>
     */
    public function groupedPermissions(): array
    {
        $menuPermissions = collect(config('menu_permissions.resources'))
            ->prepend(config('menu_permissions.home'), 'home')
            ->values();
        $actionPermissions = collect(config('menu_permissions.actions'))->flatten();

        $settingsPermissions = collect([
            'Sozlamalar',
            'Foydalanuvchilar',
            'Rollar',
            'Ruxsatlar',
            'Event tortish',
            'Xodim tortish',
        ]);

        $permissions = $this->permissionRepository->allOrdered()
            ->reject(fn (Permission $permission) => $actionPermissions->contains($permission->name));

        return [
            'Asosiy' => $permissions->filter(
                fn (Permission $permission) => $menuPermissions->contains($permission->name)
                    && ! $settingsPermissions->contains($permission->name)
            ),
            'Sozlamalar' => $permissions->filter(fn (Permission $permission) => $settingsPermissions->contains($permission->name)),
            'Boshqa' => $permissions->filter(
                fn (Permission $permission) => ! $menuPermissions->contains($permission->name)
                    && ! $settingsPermissions->contains($permission->name)
            ),
        ];
    }

    public function create(string $name, array $permissions): Role
    {
        return DB::transaction(function () use ($name, $permissions): Role {
            $role = $this->roleRepository->create([
                'name' => $name,
                'guard_name' => 'web',
            ]);

            $this->roleRepository->syncPermissions($role, $permissions);

            return $role;
        });
    }

    public function update(Role $role, string $name, array $permissions): ?string
    {
        if ($role->name === 'super-admin' && $name !== 'super-admin') {
            return 'super-admin rolini qayta nomlash mumkin emas.';
        }

        DB::transaction(function () use ($role, $name, $permissions): void {
            $this->roleRepository->update($role, ['name' => $name]);
            $this->roleRepository->syncPermissions($role, $permissions);
        });

        return null;
    }

    public function delete(Role $role): ?string
    {
        if ($role->name === 'super-admin') {
            return 'super-admin rolini o\'chirib bo\'lmaydi.';
        }

        DB::transaction(function () use ($role): void {
            $this->roleRepository->delete($role);
        });

        return null;
    }

    public function loadForShow(Role $role): Role
    {
        return $this->roleRepository->loadRelations($role, ['permissions']);
    }

    public function loadForEdit(Role $role): Role
    {
        return $this->roleRepository->loadRelations($role, ['permissions']);
    }
}
