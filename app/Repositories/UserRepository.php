<?php

namespace App\Repositories;

use App\Interfaces\UserRepositoryInterface;
use App\Models\Device;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class UserRepository implements UserRepositoryInterface
{
    public function paginate(int $perPage = 12): LengthAwarePaginator
    {
        return User::query()
            ->with(['roles', 'department:id,name', 'assignedDevices:id'])
            ->withCount('devices')
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

    public function syncAssignedDevices(User $user, array $deviceIds): void
    {
        // Muharrir faqat o‘zi ko‘radigan qurilmalarni boshqaradi; qolgan bog‘lanishlarga tegilmaydi.
        $visibleIds = Device::query()->pluck('id')->all();
        $hiddenIds = $user->assignedDevices()
            ->withoutGlobalScopes()
            ->whereNotIn('devices.id', $visibleIds)
            ->pluck('devices.id')
            ->all();

        $user->assignedDevices()->sync([
            ...$hiddenIds,
            ...array_values(array_intersect($deviceIds, $visibleIds)),
        ]);
    }

    public function devicesForLinking(): Collection
    {
        return Device::query()
            ->with('category:id,name')
            ->ordered()
            ->get(['id', 'name', 'ip_address', 'category_id', 'user_id']);
    }
}
