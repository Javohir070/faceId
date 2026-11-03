<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\OpensFormDrawer;
use App\Http\Requests\StorePermissionRequest;
use App\Http\Requests\UpdatePermissionRequest;
use App\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    use OpensFormDrawer;

    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Permissions/Index', [
            'permissions' => $this->permissionService->paginate(20),
            'drawer' => $this->drawerState($request, 'permissions', fn (int $id): ?array => Permission::query()->find($id, ['id', 'name'])?->only(['id', 'name'])),
        ]);
    }

    /** Alohida sahifa yo‘q: qo‘shish oynasi ro‘yxatdagi o‘ng panelda ochiladi. */
    public function create(): RedirectResponse
    {
        return redirect()->route('admin.permissions.index', ['create' => 1]);
    }

    public function store(StorePermissionRequest $request): RedirectResponse
    {
        $this->permissionService->create($request->validated('name'));

        return redirect()
            ->route('admin.permissions.index')
            ->with('success', 'Ruxsat muvaffaqiyatli yaratildi.');
    }

    /** Alohida ko‘rish sahifasi yo‘q — ma’lumot ro‘yxatda; eski havola ro‘yxatga qaytaradi. */
    public function show(Permission $permission): RedirectResponse
    {
        return redirect()->route('admin.permissions.index');
    }

    public function edit(Permission $permission): RedirectResponse
    {
        return redirect()->route('admin.permissions.index', ['edit' => $permission->id]);
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
