<?php

namespace App\Interfaces;

use App\Models\Device;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DeviceRepositoryInterface
{
    /**
     * @param  array{search?:?string, category_id?:?int, type?:?int, is_online?:?string}  $filters
     */
    public function paginate(int $perPage = 12, array $filters = []): LengthAwarePaginator;

    public function create(array $data): Device;

    public function update(Device $device, array $data): Device;

    public function delete(Device $device): void;

    public function loadRelations(Device $device, array $relations): Device;
}
