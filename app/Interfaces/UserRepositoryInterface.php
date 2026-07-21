<?php

namespace App\Interfaces;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function paginate(int $perPage = 12): LengthAwarePaginator;

    public function create(array $data): User;

    public function update(User $user, array $data): User;

    public function delete(User $user): void;

    public function syncRoles(User $user, array $roles): void;

    public function loadRelations(User $user, array $relations): User;

    public function countSuperAdmins(): int;
}
