<?php

namespace App\Services;

use App\Interfaces\RoleRepositoryInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly RoleRepositoryInterface $roleRepository,
    ) {}

    public function paginate(int $perPage = 12): LengthAwarePaginator
    {
        return $this->userRepository->paginate($perPage);
    }

    public function getRolesForForm(): Collection
    {
        return $this->roleRepository->allOrdered();
    }

    public function create(array $data, array $roles): User
    {
        return DB::transaction(function () use ($data, $roles): User {
            $user = $this->userRepository->create($data);
            $this->userRepository->syncRoles($user, $roles);

            return $user;
        });
    }

    public function update(User $user, array $data, array $roles): User
    {
        if (empty($data['password'])) {
            unset($data['password']);
        }

        return DB::transaction(function () use ($user, $data, $roles): User {
            $user = $this->userRepository->update($user, $data);
            $this->userRepository->syncRoles($user, $roles);

            return $user;
        });
    }

    public function delete(User $user, int $authUserId): ?string
    {
        if ($user->id === $authUserId) {
            return 'O\'zingizni o\'chira olmaysiz.';
        }

        if ($user->hasRole('super-admin') && $this->userRepository->countSuperAdmins() <= 1) {
            return 'Oxirgi super-admin foydalanuvchini o\'chirib bo\'lmaydi.';
        }

        if ($user->devices()->exists()) {
            return 'Foydalanuvchiga biriktirilgan qurilmalar bor. Avval qurilmalarni o\'chiring yoki boshqa foydalanuvchiga o\'tkazing.';
        }

        DB::transaction(function () use ($user): void {
            $this->userRepository->delete($user);
        });

        return null;
    }

    public function loadForShow(User $user): User
    {
        return $this->userRepository->loadRelations($user, ['roles', 'permissions', 'department']);
    }

    public function loadForEdit(User $user): User
    {
        return $this->userRepository->loadRelations($user, ['roles', 'department']);
    }
}
