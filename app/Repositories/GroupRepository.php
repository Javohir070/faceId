<?php

namespace App\Repositories;

use App\Interfaces\GroupRepositoryInterface;
use App\Models\Department;
use App\Models\Employee;
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
            ->visibleTo(auth()->user())
            ->when($departmentId !== null, fn ($query) => $query->where('id', $departmentId))
            ->orderBy('name')
            ->get(['id', 'name']);

        $groups = Group::query()
            ->when($departmentId !== null, fn ($query) => $query->where('department_id', $departmentId))
            ->orderBy('name')
            ->get(['id', 'name', 'department_id', 'parent_id']);

        $groupsByDepartment = $groups->groupBy('department_id');
        $groupIds = $groups->pluck('id')->all();

        // Har bir guruhdagi xodimlar (faqat o‘zida, pastki guruhlarsiz) va bo‘lim bo‘yicha jami / guruhsizlar.
        $groupCounts = Employee::query()
            ->whereIn('group_id', $groupIds)
            ->selectRaw('group_id, count(*) as total')
            ->groupBy('group_id')
            ->pluck('total', 'group_id');

        $departmentCounts = Employee::query()
            ->whereIn('department_id', $departments->pluck('id'))
            ->selectRaw('department_id, count(*) as total, sum(case when group_id is null then 1 else 0 end) as unassigned')
            ->groupBy('department_id')
            ->get()
            ->keyBy('department_id');

        return $departments
            ->map(function (Department $department) use ($groupsByDepartment, $groupIds, $departmentId, $groupCounts, $departmentCounts): ?array {
                $departmentGroups = $groupsByDepartment->get($department->id, collect());

                if ($departmentGroups->isEmpty() && $departmentId === null) {
                    return null;
                }

                $counts = $departmentCounts->get($department->id);

                return [
                    'key' => 'department-'.$department->id,
                    'type' => 'department',
                    'id' => $department->id,
                    'title' => $department->name,
                    'checkable' => false,
                    'employees_count' => (int) ($counts->total ?? 0),
                    'unassigned_count' => (int) ($counts->unassigned ?? 0),
                    'children' => $this->buildGroupNodes($departmentGroups, $groupIds, $groupCounts),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Group>  $groups
     * @param  list<int>  $groupIds
     * @param  Collection<int, int>  $employeeCounts  group_id => xodimlar soni
     * @return list<array<string, mixed>>
     */
    private function buildGroupNodes(Collection $groups, array $groupIds, Collection $employeeCounts): array
    {
        $byParent = $groups->groupBy(fn (Group $group) => $group->parent_id ?? 0);

        $node = function (Group $group) use (&$node, $byParent, $employeeCounts): array {
            return [
                'key' => 'group-'.$group->id,
                'type' => 'group',
                'id' => $group->id,
                'title' => $group->name,
                'checkable' => true,
                'employees_count' => (int) $employeeCounts->get($group->id, 0),
                'children' => $byParent->get($group->id, collect())
                    ->map(fn (Group $child): array => $node($child))
                    ->values()
                    ->all(),
            ];
        };

        return $groups
            ->filter(fn (Group $group): bool => $group->parent_id === null || ! in_array($group->parent_id, $groupIds, true))
            ->map(fn (Group $group): array => $node($group))
            ->values()
            ->all();
    }

    public function detailsForShow(Group $group, int $employeesLimit): array
    {
        $group->loadMissing('department:id,name');

        // Yuqori guruhlar zanjiri: ildizdan to bevosita parentgacha (sikl bo‘lsa to‘xtaydi).
        $path = [];
        $seen = [$group->id => true];
        $parentId = $group->parent_id;

        while ($parentId !== null && ! isset($seen[$parentId])) {
            $parent = Group::query()->find($parentId, ['id', 'name', 'parent_id']);

            if ($parent === null) {
                break;
            }

            $seen[$parent->id] = true;
            array_unshift($path, ['id' => $parent->id, 'name' => $parent->name]);
            $parentId = $parent->parent_id;
        }

        $children = $group->children()
            ->withCount(['employees', 'children'])
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id'])
            ->map(fn (Group $child): array => [
                'id' => $child->id,
                'name' => $child->name,
                'employees_count' => $child->employees_count,
                'children_count' => $child->children_count,
            ])
            ->values()
            ->all();

        $employees = $group->employees()
            ->orderBy('name')
            ->limit($employeesLimit)
            ->get(['id', 'name', 'position', 'card'])
            ->map->only(['id', 'name', 'position', 'card'])
            ->values()
            ->all();

        return [
            'id' => $group->id,
            'name' => $group->name,
            'department' => $group->department?->only(['id', 'name']),
            'parent' => $path === [] ? null : end($path),
            'path' => $path,
            'created_at' => $group->created_at?->format('d.m.Y'),
            'children' => $children,
            'employees' => $employees,
            'employees_count' => $group->employees()->count(),
        ];
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
