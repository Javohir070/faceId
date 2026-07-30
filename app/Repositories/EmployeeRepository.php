<?php

namespace App\Repositories;

use App\Interfaces\EmployeeRepositoryInterface;
use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EmployeeRepository implements EmployeeRepositoryInterface
{
    /**
     * @param  array{search?:?string, department_id?:?int, schedule_id?:?int, device_id?:?int, has_face?:?string}  $filters
     */
    public function paginate(int $perPage = 12, array $filters = []): LengthAwarePaginator
    {
        $query = Employee::query()
            ->with(['department:id,name', 'schedule:id,name', 'face:id,employee_id']);

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('card', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (! empty($filters['schedule_id'])) {
            $query->where('schedule_id', $filters['schedule_id']);
        }

        if (! empty($filters['device_id'])) {
            $query->whereHas(
                'devices',
                fn ($deviceQuery) => $deviceQuery->where('devices.id', $filters['device_id']),
            );
        }

        if (($filters['has_face'] ?? null) === '1') {
            $query->whereHas('face');
        } elseif (($filters['has_face'] ?? null) === '0') {
            $query->whereDoesntHave('face');
        }

        return $query
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
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
