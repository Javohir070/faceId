<?php

namespace App\Interfaces;

use App\Models\Device;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface
{
    public function paginate(int $perPage = 12): LengthAwarePaginator;

    public function create(array $data): User;

    public function update(User $user, array $data): User;

    public function delete(User $user): void;

    public function syncRoles(User $user, array $roles): void;

    public function loadRelations(User $user, array $relations): User;

    public function countSuperAdmins(): int;

    /**
     * Foydalanuvchiga bog‘langan qurilmalarni almashtiradi. Muharrirga ko‘rinmaydigan bog‘lanishlar saqlanadi.
     *
     * @param  list<int>  $deviceIds
     */
    public function syncAssignedDevices(User $user, array $deviceIds): void;

    /**
     * Bog‘lash oynasi uchun muharrirga ko‘rinadigan qurilmalar.
     *
     * @return Collection<int, Device>
     */
    public function devicesForLinking(): Collection;
}
