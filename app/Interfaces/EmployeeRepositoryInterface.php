<?php

namespace App\Interfaces;

use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface EmployeeRepositoryInterface
{
    /**
     * @param  array{search?:?string, department_ids?:list<int>, group_ids?:list<int>, schedule_id?:?int, device_id?:?int, has_face?:?string, validity?:?string}  $filters
     * @return Builder<Employee>
     */
    public function filteredQuery(array $filters = []): Builder;

    /**
     * @param  array{search?:?string, department_ids?:list<int>, group_ids?:list<int>, schedule_id?:?int, device_id?:?int, has_face?:?string, validity?:?string}  $filters
     */
    public function paginate(int $perPage = 20, array $filters = []): LengthAwarePaginator;

    public function create(array $data): Employee;

    public function update(Employee $employee, array $data): Employee;

    public function delete(Employee $employee): void;

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Employee>
     */
    public function findMany(array $ids): Collection;

    public function loadRelations(Employee $employee, array $relations): Employee;
}
