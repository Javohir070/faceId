<?php

namespace App\Services;

use App\Interfaces\PermissionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class PermissionService
{
    public function __construct(
        private readonly PermissionRepositoryInterface $permissionRepository,
    ) {}

    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return $this->permissionRepository->paginate($perPage);
    }

    public function create(string $name): Permission
    {
        return DB::transaction(fn (): Permission => $this->permissionRepository->create([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }

    public function update(Permission $permission, array $data): Permission
    {
        return DB::transaction(fn (): Permission => $this->permissionRepository->update($permission, $data));
    }

    public function delete(Permission $permission): void
    {
        DB::transaction(function () use ($permission): void {
            $this->permissionRepository->delete($permission);
        });
    }
}
