<?php

namespace App\Services;

use App\Interfaces\DepartmentRepositoryInterface;
use App\Interfaces\GroupRepositoryInterface;
use App\Models\Group;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GroupService
{
    public function __construct(
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly DepartmentRepositoryInterface $departmentRepository,
    ) {}

    /**
     * @param  array{department_id?: int|null}  $filters
     */
    public function paginate(int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        return $this->groupRepository->paginate($perPage, $filters);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function treeForIndex(?int $departmentId = null): array
    {
        return $this->groupRepository->treeForIndex($departmentId);
    }

    /**
     * @param  list<int>  $groupIds
     * @return array{deleted: int, errors: list<string>}
     */
    public function deleteMany(array $groupIds): array
    {
        $deleted = 0;
        $errors = [];

        $groups = Group::query()
            ->whereIn('id', $groupIds)
            ->withCount('children')
            ->get()
            ->sortBy('children_count')
            ->values();

        foreach ($groups as $group) {
            if ($error = $this->delete($group)) {
                $errors[] = "{$group->name}: {$error}";

                continue;
            }

            $deleted++;
        }

        return ['deleted' => $deleted, 'errors' => $errors];
    }

    public function getDepartmentsForForm(): Collection
    {
        return $this->departmentRepository->allOrdered();
    }

    public function getGroupsForForm(?int $excludeId = null): Collection
    {
        return $this->groupRepository->allOrdered()
            ->when($excludeId !== null, fn (Collection $groups) => $groups->reject(
                fn (Group $group): bool => $group->id === $excludeId,
            ))
            ->values();
    }

    public function create(array $data): Group
    {
        return DB::transaction(fn (): Group => $this->groupRepository->create($data));
    }

    public function update(Group $group, array $data): Group
    {
        return DB::transaction(fn (): Group => $this->groupRepository->update($group, $data));
    }

    public function delete(Group $group): ?string
    {
        if ($this->groupRepository->hasChildren($group)) {
            return 'Guruhda pastki guruhlar bor. Avval ularni o‘chiring yoki boshqa parentga o‘tkazing.';
        }

        DB::transaction(function () use ($group): void {
            $this->groupRepository->delete($group);
        });

        return null;
    }
}
