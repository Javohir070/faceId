<?php

namespace App\Repositories;

use App\Interfaces\PermissionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

class PermissionRepository implements PermissionRepositoryInterface
{
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function allOrdered(): Collection
    {
        return Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): Permission
    {
        return Permission::query()->create($data);
    }

    public function update(Permission $permission, array $data): Permission
    {
        $permission->update($data);

        return $permission->fresh();
    }

    public function delete(Permission $permission): void
    {
        $permission->delete();
    }
}
