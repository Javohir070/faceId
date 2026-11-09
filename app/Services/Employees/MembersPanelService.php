<?php

namespace App\Services\Employees;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Bo‘lim yoki guruh nomi bosilganda o‘ngdan ochiladigan xodimlar paneli.
 * Group va Employee global scope'lari ishlaydi: foydalanuvchi faqat o‘z bo‘limini ochadi.
 *
 * Xodimlar guruhlar daraxti tartibida keladi (nomi bo‘yicha, pastki guruh otasidan keyin),
 * guruhsizlar oxirida; panel ularni guruh bo‘limlariga ajratib ko‘rsatadi.
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

        $order = $this->groupOrder($group->department_id, $group->id);
        $groupIds = array_keys($order);

        return $this->panel(
            $key,
            $group->name,
            $group->department?->name,
            Employee::query()->whereIn('group_id', $groupIds),
            $order,
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
            $this->groupOrder($department->id, null),
            ['department_ids' => [$department->id]],
        );
    }

    /**
     * Guruhlar daraxti chuqurlik bo‘yicha: ota, keyin uning pastki guruhlari (nomi bo‘yicha).
     * $rootId berilsa faqat shu guruh va uning ostidagilar. Sikl bo‘lsa takror kirilmaydi.
     *
     * @return array<int, array{index: int, path: string}>
     */
    private function groupOrder(?int $departmentId, ?int $rootId): array
    {
        $groups = Group::query()
            ->where('department_id', $departmentId)
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        $known = $groups->pluck('id')->flip();
        $children = $groups->groupBy(fn (Group $group): int => $group->parent_id ?? 0);
        $order = [];

        $visit = function (Group $group, string $parentPath) use (&$visit, &$order, $children): void {
            if (isset($order[$group->id])) {
                return;
            }

            $path = $parentPath === '' ? $group->name : $parentPath.' › '.$group->name;
            $order[$group->id] = ['index' => count($order), 'path' => $path];

            foreach ($children->get($group->id, collect()) as $child) {
                $visit($child, $path);
            }
        };

        $roots = $rootId !== null
            ? $groups->where('id', $rootId)
            : $groups->filter(fn (Group $group): bool => $group->parent_id === null || ! $known->has($group->parent_id));

        foreach ($roots as $root) {
            $visit($root, '');
        }

        return $order;
    }

    /**
     * @param  Builder<Employee>  $query
     * @param  array<int, array{index: int, path: string}>  $order
     * @param  array<string, list<int>>  $filter  Xodimlar sahifasidagi shu filtr
     * @return array<string, mixed>
     */
    private function panel(string $key, string $title, ?string $subtitle, Builder $query, array $order, array $filter): array
    {
        $total = (clone $query)->count();

        if ($order !== []) {
            $cases = collect($order)
                ->map(fn (array $item, int $groupId): string => 'WHEN '.$groupId.' THEN '.$item['index'])
                ->implode(' ');
            $query->orderByRaw('CASE group_id '.$cases.' ELSE '.count($order).' END');
        }

        // Rasmning o‘zi emas, faqat bor-yo‘qligi va yangilangan vaqti: rasm alohida havola orqali yuklanadi.
        $employees = $query
            ->withMax('face', 'updated_at')
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get(['id', 'name', 'position', 'group_id', 'valid_from_dt', 'valid_to_dt'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->name,
                'position' => $employee->position,
                'group' => $order[$employee->group_id]['path'] ?? null,
                'validity' => $employee->validityStatus(),
                'face_url' => $employee->face_max_updated_at === null ? null : route('admin.employees.face', [
                    'employee' => $employee->id,
                    'v' => Carbon::parse($employee->face_max_updated_at)->timestamp,
                ], false),
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
