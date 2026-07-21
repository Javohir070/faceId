<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePermissionRequest;
use App\Http\Requests\UpdatePermissionRequest;
use App\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Permissions/Index', [
            'permissions' => $this->permissionService->paginate(10),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Permissions/Form');
    }

    public function store(StorePermissionRequest $request): RedirectResponse
    {
        $this->permissionService->create($request->validated('name'));

        return redirect()
            ->route('admin.permissions.index')
            ->with('success', 'Ruxsat muvaffaqiyatli yaratildi.');
    }

    public function show(Permission $permission): Response
    {
        return Inertia::render('Admin/Permissions/Show', [
            'permission' => $permission->only(['id', 'name', 'guard_name', 'created_at']),
        ]);
    }

    public function edit(Permission $permission): Response
    {
        return Inertia::render('Admin/Permissions/Form', [
            'permission' => $permission->only(['id', 'name']),
        ]);
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): RedirectResponse
    {
        $this->permissionService->update($permission, $request->validated());

        return redirect()
            ->route('admin.permissions.index')
            ->with('success', 'Ruxsat muvaffaqiyatli yangilandi.');
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        $this->permissionService->delete($permission);

        return redirect()
            ->route('admin.permissions.index')
            ->with('success', 'Ruxsat muvaffaqiyatli o\'chirildi.');
    }
}
