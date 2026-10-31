<?php

namespace App\Repositories;

use App\Interfaces\EmployeeRepositoryInterface;
use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class EmployeeRepository implements EmployeeRepositoryInterface
{
    /**
     * @param  array{search?:?string, department_ids?:list<int>, group_ids?:list<int>, schedule_id?:?int, device_id?:?int, has_face?:?string}  $filters
     * @return Builder<Employee>
     */
    public function filteredQuery(array $filters = []): Builder
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

        $departmentIds = array_values(array_unique(array_filter(array_map(
            'intval',
            $filters['department_ids'] ?? [],
        ))));
        $groupIds = array_values(array_unique(array_filter(array_map(
            'intval',
            $filters['group_ids'] ?? [],
        ))));

        if ($departmentIds !== [] || $groupIds !== []) {
            $query->where(function (Builder $builder) use ($departmentIds, $groupIds): void {
                if ($departmentIds !== []) {
                    $builder->whereIn('department_id', $departmentIds);
                }

                if ($groupIds !== []) {
                    if ($departmentIds !== []) {
                        $builder->orWhereIn('group_id', $groupIds);
                    } else {
                        $builder->whereIn('group_id', $groupIds);
                    }
                }
            });
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

        return $query->latest();
    }

    /**
     * @param  array{search?:?string, department_ids?:list<int>, group_ids?:list<int>, schedule_id?:?int, device_id?:?int, has_face?:?string}  $filters
     */
    public function paginate(int $perPage = 12, array $filters = []): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
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

    public function findMany(array $ids): Collection
    {
        return Employee::query()->whereIn('id', $ids)->get();
    }

    public function loadRelations(Employee $employee, array $relations): Employee
    {
        return $employee->load($relations);
    }
}
