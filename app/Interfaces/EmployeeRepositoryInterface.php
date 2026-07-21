<?php

namespace App\Interfaces;

use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface EmployeeRepositoryInterface
{
    public function paginate(int $perPage = 12): LengthAwarePaginator;

    public function create(array $data): Employee;

    public function update(Employee $employee, array $data): Employee;

    public function delete(Employee $employee): void;

    public function loadRelations(Employee $employee, array $relations): Employee;
}
