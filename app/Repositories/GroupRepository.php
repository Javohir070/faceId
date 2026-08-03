<?php

namespace App\Repositories;

use App\Interfaces\GroupRepositoryInterface;
use App\Models\Department;
use App\Models\Group;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class GroupRepository implements GroupRepositoryInterface
{
    public function paginate(int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        return Group::query()
            ->with(['department:id,name', 'parent:id,name'])
            ->when(
                filled($filters['department_id'] ?? null),
                fn ($query) => $query->where('department_id', $filters['department_id']),
            )
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function treeForIndex(?int $departmentId = null): array
    {
        $departments = Department::query()
            ->when($departmentId !== null, fn ($query) => $query->where('id', $departmentId))
            ->orderBy('name')
            ->get(['id', 'name']);

        $groups = Group::query()
            ->when($departmentId !== null, fn ($query) => $query->where('department_id', $departmentId))
            ->orderBy('name')
            ->get(['id', 'name', 'department_id', 'parent_id']);

        $groupsByDepartment = $groups->groupBy('department_id');
        $groupIds = $groups->pluck('id')->all();

        return $departments
            ->map(function (Department $department) use ($groupsByDepartment, $groupIds, $departmentId): ?array {
                $departmentGroups = $groupsByDepartment->get($department->id, collect());

                if ($departmentGroups->isEmpty() && $departmentId === null) {
                    return null;
                }

                return [
                    'key' => 'department-'.$department->id,
                    'type' => 'department',
                    'id' => $department->id,
                    'title' => $department->name,
                    'checkable' => false,
                    'children' => $this->buildGroupNodes($departmentGroups, $groupIds),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Group>  $groups
     * @param  list<int>  $groupIds
     * @return list<array<string, mixed>>
     */
    private function buildGroupNodes(Collection $groups, array $groupIds): array
    {
        $byParent = $groups->groupBy(fn (Group $group) => $group->parent_id ?? 0);

        $build = function (int $parentId) use (&$build, $byParent): array {
            return ($byParent->get($parentId, collect()))
                ->map(fn (Group $group): array => [
                    'key' => 'group-'.$group->id,
                    'type' => 'group',
                    'id' => $group->id,
                    'title' => $group->name,
                    'checkable' => true,
                    'children' => $build($group->id),
                ])
                ->values()
                ->all();
        };

        return $groups
            ->filter(fn (Group $group): bool => $group->parent_id === null || ! in_array($group->parent_id, $groupIds, true))
            ->map(fn (Group $group): array => [
                'key' => 'group-'.$group->id,
                'type' => 'group',
                'id' => $group->id,
                'title' => $group->name,
                'checkable' => true,
                'children' => $build($group->id),
            ])
            ->values()
            ->all();
    }

    public function allOrdered(?int $departmentId = null): Collection
    {
        return Group::query()
            ->when($departmentId !== null, fn ($query) => $query->where('department_id', $departmentId))
            ->orderBy('name')
            ->get(['id', 'name', 'department_id', 'parent_id']);
    }

    public function create(array $data): Group
    {
        return Group::query()->create($data);
    }

    public function update(Group $group, array $data): Group
    {
        $group->update($data);

        return $group->fresh(['department', 'parent']);
    }

    public function delete(Group $group): void
    {
        $group->delete();
    }

    public function hasChildren(Group $group): bool
    {
        return $group->children()->exists();
    }
}
