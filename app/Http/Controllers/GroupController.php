<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\OpensFormDrawer;
use App\Http\Requests\BulkDestroyGroupsRequest;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use App\Models\Group;
use App\Services\Employees\MembersPanelService;
use App\Services\GroupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    use OpensFormDrawer;

    public function __construct(
        private readonly GroupService $groupService,
        private readonly MembersPanelService $membersPanel,
    ) {}

    public function index(Request $request): Response
    {
        $filters = [
            'department_id' => $request->integer('department_id') ?: null,
        ];

        $departments = $this->groupService->getDepartmentsForForm();
        $groups = $this->groupService->getGroupsForForm();

        $drawer = $this->drawerState($request, 'groups', fn (int $id): ?array => Group::query()->find($id)?->only(['id', 'name', 'department_id', 'parent_id']));

        if (($drawer['mode'] ?? null) === 'create') {
            // ?parent_id= (pastki guruh qo‘shish) yoki ?department_id= — faqat foydalanuvchiga ko‘rinadiganlari olinadi.
            $parent = $groups->firstWhere('id', $request->integer('parent_id') ?: null);
            $department = $departments->firstWhere('id', $parent?->department_id ?? ($request->integer('department_id') ?: null));

            $drawer['defaults'] = [
                'department_id' => $department?->id,
                'parent_id' => $department ? $parent?->id : null,
            ];
        }

        return Inertia::render('Admin/Groups/Index', [
            'tree' => $this->groupService->treeForIndex($filters['department_id']),
            'filters' => $filters,
            'departments' => $departments->map->only(['id', 'name'])->values(),
            'departmentLocked' => $request->user()->isDepartmentScoped(),
            'groups' => $groups->map->only(['id', 'name', 'department_id'])->values(),
            'drawer' => $drawer,
            // Guruh yoki bo‘lim nomi bosilganda o‘ngdan ochiladigan xodimlar paneli.
            'members' => Inertia::optional(fn (): ?array => $this->membersPanel->resolve($request->query('members'))),
        ]);
    }

    /** Alohida sahifa yo‘q: qo‘shish oynasi ro‘yxatdagi o‘ng panelda ochiladi. */
    public function create(Request $request): RedirectResponse
    {
        return redirect()->route('admin.groups.index', [
            'create' => 1,
            ...array_filter($request->only(['parent_id', 'department_id'])),
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

    public function edit(Group $group): RedirectResponse
    {
        return redirect()->route('admin.groups.index', ['edit' => $group->id]);
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
