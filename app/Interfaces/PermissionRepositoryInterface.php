<?php

namespace App\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

interface PermissionRepositoryInterface
{
    public function paginate(int $perPage = 20): LengthAwarePaginator;

    public function allOrdered(): Collection;

    public function create(array $data): Permission;

    public function update(Permission $permission, array $data): Permission;

    public function delete(Permission $permission): void;
}
