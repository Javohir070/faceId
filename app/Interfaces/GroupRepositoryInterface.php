<?php

namespace App\Interfaces;

use App\Models\Group;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface GroupRepositoryInterface
{
    /**
     * @param  array{department_id?: int|null}  $filters
     */
    public function paginate(int $perPage = 20, array $filters = []): LengthAwarePaginator;

    /**
     * @return list<array{key: string, type: string, id: int, title: string, checkable: bool, employees_count: int, unassigned_count: int, children: list<array<string, mixed>>}>
     */
    public function treeForIndex(?int $departmentId = null): array;

    /**
     * Guruh sahifasi uchun: yuqori guruhlar zanjiri, pastki guruhlar va birinchi $employeesLimit ta xodim.
     *
     * @return array<string, mixed>
     */
    public function detailsForShow(Group $group, int $employeesLimit): array;

    public function allOrdered(?int $departmentId = null): Collection;

    public function create(array $data): Group;

    public function update(Group $group, array $data): Group;

    public function delete(Group $group): void;

    public function hasChildren(Group $group): bool;
}
