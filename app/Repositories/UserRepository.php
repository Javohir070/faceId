<?php

namespace App\Repositories;

use App\Interfaces\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserRepository implements UserRepositoryInterface
{
    public function paginate(int $perPage = 12): LengthAwarePaginator
    {
        return User::query()
            ->with(['roles', 'department:id,name'])
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): User
    {
        return User::query()->create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh();
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    public function syncRoles(User $user, array $roles): void
    {
        $user->syncRoles($roles);
    }

    public function loadRelations(User $user, array $relations): User
    {
        return $user->load($relations);
    }

    public function countSuperAdmins(): int
    {
        return User::role('super-admin')->count();
    }
}
