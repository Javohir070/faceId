<?php

namespace App\Services\Employees;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Group;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bo‘lim yoki guruh nomi bosilganda o‘ngdan ochiladigan xodimlar paneli.
 * Group va Employee global scope'lari ishlaydi: foydalanuvchi faqat o‘z bo‘limini ochadi.
 */
class MembersPanelService
{
    /** Panel uzun ro‘yxat uchun emas: qolgani Xodimlar sahifasida filtr bilan ochiladi. */
    private const LIMIT = 500;

    /**
     * @param  string|null  $key  "group:5" yoki "department:2"
     * @return array<string, mixed>|null
     */
    public function resolve(?string $key): ?array
    {
        if ($key === null || preg_match('/^(group|department):(\d+)$/', $key, $matches) !== 1) {
            return null;
        }

        return $matches[1] === 'group'
            ? $this->forGroup((int) $matches[2], $key)
            : $this->forDepartment((int) $matches[2], $key);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function forGroup(int $id, string $key): ?array
    {
        $group = Group::query()->with('department:id,name')->find($id);

        if ($group === null) {
            return null;
        }

        $groupIds = $this->withSubgroups($group);

        return $this->panel(
            $key,
            $group->name,
            $group->department?->name,
            Employee::query()->whereIn('group_id', $groupIds),
            ['group_ids' => $groupIds],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function forDepartment(int $id, string $key): ?array
    {
        $department = Department::query()->visibleTo(auth()->user())->find($id);

        if ($department === null) {
            return null;
        }

        return $this->panel(
            $key,
            $department->name,
            null,
            Employee::query()->where('department_id', $department->id),
            ['department_ids' => [$department->id]],
        );
    }

    /**
     * Guruh va uning barcha pastki guruhlari (sikl bo‘lsa to‘xtaydi).
     *
     * @return list<int>
     */
    private function withSubgroups(Group $group): array
    {
        $ids = [$group->id];
        $frontier = [$group->id];

        while ($frontier !== []) {
            $frontier = Group::query()
                ->whereIn('parent_id', $frontier)
                ->whereNotIn('id', $ids)
                ->pluck('id')
                ->all();

            $ids = [...$ids, ...$frontier];
        }

        return $ids;
    }

    /**
     * @param  Builder<Employee>  $query
     * @param  array<string, list<int>>  $filter  Xodimlar sahifasidagi shu filtr
     * @return array<string, mixed>
     */
    private function panel(string $key, string $title, ?string $subtitle, Builder $query, array $filter): array
    {
        $total = (clone $query)->count();

        $employees = $query
            ->with('group:id,name')
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get(['id', 'name', 'position', 'group_id', 'valid_from_dt', 'valid_to_dt'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->name,
                'position' => $employee->position,
                'group' => $employee->group?->name,
                'validity' => $employee->validityStatus(),
            ])
            ->values()
            ->all();

        return [
            'key' => $key,
            'title' => $title,
            'subtitle' => $subtitle,
            'total' => $total,
            'employees' => $employees,
            'filter' => $filter,
        ];
    }
}
