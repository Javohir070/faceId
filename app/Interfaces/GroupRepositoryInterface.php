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
     * @return list<array{key: string, type: string, id: int, title: string, checkable: bool, children: list<array<string, mixed>>}>
     */
    public function treeForIndex(?int $departmentId = null): array;

    public function allOrdered(?int $departmentId = null): Collection;

    public function create(array $data): Group;

    public function update(Group $group, array $data): Group;

    public function delete(Group $group): void;

    public function hasChildren(Group $group): bool;
}
