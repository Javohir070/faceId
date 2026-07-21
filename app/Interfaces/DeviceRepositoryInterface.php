<?php

namespace App\Interfaces;

use App\Models\Device;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DeviceRepositoryInterface
{
    public function paginate(int $perPage = 12): LengthAwarePaginator;

    public function create(array $data): Device;

    public function update(Device $device, array $data): Device;

    public function delete(Device $device): void;

    public function loadRelations(Device $device, array $relations): Device;
}
