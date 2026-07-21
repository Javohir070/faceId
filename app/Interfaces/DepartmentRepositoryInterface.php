<?php

namespace App\Interfaces;

use App\Models\Department;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface DepartmentRepositoryInterface
{
    public function paginate(int $perPage = 20): LengthAwarePaginator;

    public function allOrdered(): Collection;

    public function create(array $data): Department;

    public function update(Department $department, array $data): Department;

    public function delete(Department $department): void;
}
