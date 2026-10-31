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

    public function create(Request $request): Response
    {
        $departments = $this->groupService->getDepartmentsForForm();
        $groups = $this->groupService->getGroupsForForm();

        // ?parent_id= (pastki guruh qo‘shish) yoki ?department_id= — faqat foydalanuvchiga ko‘rinadiganlari olinadi.
        $parent = $groups->firstWhere('id', $request->integer('parent_id') ?: null);
        $department = $departments->firstWhere('id', $parent?->department_id ?? ($request->integer('department_id') ?: null));

        return Inertia::render('Admin/Groups/Form', [
            'departments' => $departments->map->only(['id', 'name'])->values(),
            'groups' => $groups->map->only(['id', 'name', 'department_id'])->values(),
            'defaults' => [
                'department_id' => $department?->id,
                'parent_id' => $department ? $parent?->id : null,
            ],
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
        return Inertia::render('Admin/Groups/Show', [
            'group' => $this->groupService->detailsForShow($group),
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
