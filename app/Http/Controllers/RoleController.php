<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Services\RoleService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Roles/Index', [
            'roles' => $this->roleService->paginate(12),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Roles/Form', [
            'permissions' => $this->serializeGroupedPermissions(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->roleService->create(
            $data['name'],
            $data['permissions'] ?? [],
        );

        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'Rol muvaffaqiyatli yaratildi.');
    }

    public function show(Role $role): Response
    {
        $role = $this->roleService->loadForShow($role);

        return Inertia::render('Admin/Roles/Show', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->map->only(['id', 'name'])->values(),
            ],
        ]);
    }

    public function edit(Role $role): Response
    {
        $role = $this->roleService->loadForEdit($role);

        return Inertia::render('Admin/Roles/Form', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values(),
            ],
            'permissions' => $this->serializeGroupedPermissions(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $data = $request->validated();

        if ($error = $this->roleService->update($role, $data['name'], $data['permissions'] ?? [])) {
            return redirect()->back()->with('error', $error);
        }

        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'Rol muvaffaqiyatli yangilandi.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($error = $this->roleService->delete($role)) {
            return redirect()->back()->with('error', $error);
        }

        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'Rol muvaffaqiyatli o\'chirildi.');
    }

    /**
     * @return array<string, array<int, array{id: int, name: string}>>
     */
    private function serializeGroupedPermissions(): array
    {
        return collect($this->roleService->groupedPermissions())
            ->map(fn ($group) => $group->values()->map(fn (Permission $permission) => [
                'id' => $permission->id,
                'name' => $permission->name,
            ])->all())
            ->all();
    }
}
