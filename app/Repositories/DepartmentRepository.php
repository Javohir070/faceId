<?php

namespace App\Repositories;

use App\Interfaces\DepartmentRepositoryInterface;
use App\Models\Department;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DepartmentRepository implements DepartmentRepositoryInterface
{
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return Department::query()
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function allOrdered(): Collection
    {
        return Department::query()
            ->visibleTo(auth()->user())
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): Department
    {
        return Department::query()->create($data);
    }

    public function update(Department $department, array $data): Department
    {
        $department->update($data);

        return $department->fresh();
    }

    public function delete(Department $department): void
    {
        $department->delete();
    }

    public function hasEmployees(Department $department): bool
    {
        // Mehmonlar ham hisoblanadi — aks holda bo‘lim o‘chirilib, FK xatosi beradi.
        return $department->employees()->withVisitors()->exists();
    }
}
