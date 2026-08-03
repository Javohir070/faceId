<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkDestroyGroupsRequest;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use App\Models\Group;
use App\Services\GroupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    public function __construct(
        private readonly GroupService $groupService,
    ) {}

    public function index(Request $request): Response
    {
        $filters = [
            'department_id' => $request->integer('department_id') ?: null,
        ];

        return Inertia::render('Admin/Groups/Index', [
            'tree' => $this->groupService->treeForIndex($filters['department_id']),
            'filters' => $filters,
            'departments' => $this->groupService->getDepartmentsForForm()->map->only(['id', 'name'])->values(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Groups/Form', [
            'departments' => $this->groupService->getDepartmentsForForm()->map->only(['id', 'name'])->values(),
            'groups' => $this->groupService->getGroupsForForm()->map->only(['id', 'name', 'department_id'])->values(),
        ]);
    }

    public function store(StoreGroupRequest $request): RedirectResponse
    {
        $this->groupService->create($request->validated());

        return redirect()
            ->route('admin.groups.index')
            ->with('success', 'Guruh muvaffaqiyatli yaratildi.');
    }

    public function show(Group $group): Response
    {
        $group->load(['department:id,name', 'parent:id,name']);

        return Inertia::render('Admin/Groups/Show', [
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'department' => $group->department?->only(['id', 'name']),
                'parent' => $group->parent?->only(['id', 'name']),
                'created_at' => $group->created_at,
            ],
        ]);
    }

    public function edit(Group $group): Response
    {
        return Inertia::render('Admin/Groups/Form', [
            'group' => $group->only(['id', 'name', 'department_id', 'parent_id']),
            'departments' => $this->groupService->getDepartmentsForForm()->map->only(['id', 'name'])->values(),
            'groups' => $this->groupService->getGroupsForForm($group->id)->map->only(['id', 'name', 'department_id'])->values(),
        ]);
    }

    public function update(UpdateGroupRequest $request, Group $group): RedirectResponse
    {
        $this->groupService->update($group, $request->validated());

        return redirect()
            ->route('admin.groups.index')
            ->with('success', 'Guruh muvaffaqiyatli yangilandi.');
    }

    public function destroy(Group $group): RedirectResponse
    {
        if ($error = $this->groupService->delete($group)) {
            return redirect()->back()->with('error', $error);
        }

        return redirect()
            ->route('admin.groups.index')
            ->with('success', 'Guruh muvaffaqiyatli o‘chirildi.');
    }

    public function bulkDestroy(BulkDestroyGroupsRequest $request): RedirectResponse
    {
        $result = $this->groupService->deleteMany($request->validated('group_ids'));

        if ($result['deleted'] > 0 && $result['errors'] === []) {
            return redirect()
                ->route('admin.groups.index')
                ->with('success', "{$result['deleted']} ta guruh o‘chirildi.");
        }

        if ($result['deleted'] > 0) {
            return redirect()
                ->route('admin.groups.index')
                ->with('success', "{$result['deleted']} ta guruh o‘chirildi.")
                ->with('error', implode(' ', $result['errors']));
        }

        return redirect()
            ->route('admin.groups.index')
            ->with('error', $result['errors'][0] ?? 'Guruhlar o‘chirilmadi.');
    }
}
