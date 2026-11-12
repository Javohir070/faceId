<?php

namespace App\Services\Groups;

use App\Models\Group;

/**
 * Bo‘lim guruhlari daraxti tartibi: ota, keyin uning pastki guruhlari (nomi bo‘yicha).
 * Group global scope'i ishlaydi: foydalanuvchi faqat o‘z bo‘limi guruhlarini oladi.
 */
class GroupTree
{
    /**
     * $rootId berilsa faqat shu guruh va uning ostidagilar. Sikl bo‘lsa takror kirilmaydi.
     *
     * @return array<int, array{index: int, path: string}>
     */
    public function order(int $departmentId, ?int $rootId = null): array
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
}
