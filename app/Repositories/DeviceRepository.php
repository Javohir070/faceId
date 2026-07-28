<?php

namespace App\Repositories;

use App\Interfaces\DeviceRepositoryInterface;
use App\Models\Device;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DeviceRepository implements DeviceRepositoryInterface
{
    public function paginate(int $perPage = 12): LengthAwarePaginator
    {
        return Device::query()
            ->with(['category:id,name', 'owner:id,name'])
            ->ordered()
            ->paginate($perPage);
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
