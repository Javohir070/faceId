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
     * @return array<string, Collection<int, Permission>>
     */
    public function groupedPermissions(): array
    {
        $menuPermissions = collect(config('menu_permissions.resources'))
            ->prepend(config('menu_permissions.home'), 'home')
            ->values();

        $settingsPermissions = collect(['Sozlamalar', 'Foydalanuvchilar', 'Rollar', 'Ruxsatlar']);

        $permissions = $this->permissionRepository->allOrdered();

        return [
            'Asosiy' => $permissions->filter(fn (Permission $permission) => $menuPermissions->contains($permission->name)),
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
