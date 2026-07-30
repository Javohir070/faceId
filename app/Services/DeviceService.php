<?php

namespace App\Services;

use App\Interfaces\CategoryRepositoryInterface;
use App\Interfaces\DeviceRepositoryInterface;
use App\Models\Device;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeviceService
{
    public function __construct(
        private readonly DeviceRepositoryInterface $deviceRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
    ) {}

    /**
     * @param  array{search?:?string, category_id?:?int, type?:?int, is_online?:?string}  $filters
     */
    public function paginate(int $perPage = 12, array $filters = []): LengthAwarePaginator
    {
        return $this->deviceRepository->paginate($perPage, $filters);
    }

    public function getCategoriesForForm(): Collection
    {
        return $this->categoryRepository->allOrdered();
    }

    public function create(array $data): Device
    {
        return DB::transaction(fn (): Device => $this->deviceRepository->create($data));
    }

    public function update(Device $device, array $data): Device
    {
        if (empty($data['password'])) {
            unset($data['password']);
        }

        return DB::transaction(fn (): Device => $this->deviceRepository->update($device, $data));
    }

    public function delete(Device $device): void
    {
        DB::transaction(function () use ($device): void {
            $this->deviceRepository->delete($device);
        });
    }

    public function loadForShow(Device $device): Device
    {
        return $this->deviceRepository->loadRelations($device, ['category', 'owner', 'employees.department']);
    }

    public function loadForEdit(Device $device): Device
    {
        return $this->deviceRepository->loadRelations($device, ['category']);
    }

    public function typeLabel(Device $device): string
    {
        return $device->typeLabel();
    }
}
