<?php

namespace App\Repositories;

use App\Interfaces\DeviceRepositoryInterface;
use App\Models\Device;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DeviceRepository implements DeviceRepositoryInterface
{
    /**
     * @param  array{search?:?string, category_id?:?int, type?:?int, is_online?:?string}  $filters
     */
    public function paginate(int $perPage = 12, array $filters = []): LengthAwarePaginator
    {
        $query = Device::query()
            ->with(['category:id,name', 'owner:id,name']);

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('ip_address', 'like', '%'.$search.'%')
                    ->orWhere('user', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (($filters['is_online'] ?? null) === '1') {
            $query->where('is_online', true);
        } elseif (($filters['is_online'] ?? null) === '0') {
            $query->where('is_online', false);
        }

        return $query
            ->ordered()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): Device
    {
        return Device::query()->create($data);
    }

    public function update(Device $device, array $data): Device
    {
        $device->update($data);

        return $device->fresh();
    }

    public function delete(Device $device): void
    {
        $device->delete();
    }

    public function loadRelations(Device $device, array $relations): Device
    {
        return $device->load($relations);
    }
}
