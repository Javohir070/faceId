<?php

namespace App\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

interface RoleRepositoryInterface
{
    public function paginate(int $perPage = 12): LengthAwarePaginator;

    public function allOrdered(): Collection;

    public function create(array $data): Role;

    public function update(Role $role, array $data): Role;

    public function delete(Role $role): void;

    public function syncPermissions(Role $role, array $permissions): void;

    public function loadRelations(Role $role, array $relations): Role;
}
