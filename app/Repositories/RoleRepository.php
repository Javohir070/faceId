<?php

namespace App\Repositories;

use App\Interfaces\RoleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class RoleRepository implements RoleRepositoryInterface
{
    public function paginate(int $perPage = 12): LengthAwarePaginator
    {
        return Role::query()
            ->with('permissions')
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function allOrdered(): Collection
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): Role
    {
        return Role::query()->create($data);
    }

    public function update(Role $role, array $data): Role
    {
        $role->update($data);

        return $role->fresh();
    }

    public function delete(Role $role): void
    {
        $role->delete();
    }

    public function syncPermissions(Role $role, array $permissions): void
    {
        $role->syncPermissions($permissions);
    }

    public function loadRelations(Role $role, array $relations): Role
    {
        return $role->load($relations);
    }
}
