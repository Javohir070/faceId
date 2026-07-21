<?php

namespace App\Repositories;

use App\Interfaces\EmployeeRepositoryInterface;
use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EmployeeRepository implements EmployeeRepositoryInterface
{
    public function paginate(int $perPage = 12): LengthAwarePaginator
    {
        return Employee::query()
            ->with(['department:id,name', 'schedule:id,name', 'face:id,employee_id'])
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): Employee
    {
        return Employee::query()->create($data);
    }

    public function update(Employee $employee, array $data): Employee
    {
        $employee->update($data);

        return $employee->fresh();
    }

    public function delete(Employee $employee): void
    {
        $employee->delete();
    }

    public function loadRelations(Employee $employee, array $relations): Employee
    {
        return $employee->load($relations);
    }
}
