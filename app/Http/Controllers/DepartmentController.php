<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\OpensFormDrawer;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\Department;
use App\Services\DepartmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    use OpensFormDrawer;

    public function __construct(
        private readonly DepartmentService $departmentService,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Departments/Index', [
            'departments' => $this->departmentService->paginate(10),
            'drawer' => $this->drawerState($request, fn (int $id): ?array => Department::query()->visibleTo($request->user())->find($id, ['id', 'name'])?->only(['id', 'name'])),
        ]);
    }

    /** Alohida sahifa yo‘q: qo‘shish oynasi ro‘yxatdagi o‘ng panelda ochiladi. */
    public function create(): RedirectResponse
    {
        return redirect()->route('admin.departments.index', ['create' => 1]);
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $this->departmentService->create($request->validated());

        return redirect()
            ->route('admin.departments.index')
            ->with('success', "Bo'lim muvaffaqiyatli yaratildi.");
    }

    /** Alohida ko‘rish sahifasi yo‘q — ma’lumot ro‘yxatda; eski havola ro‘yxatga qaytaradi. */
    public function show(Department $department): RedirectResponse
    {
        return redirect()->route('admin.departments.index');
    }

    public function edit(Department $department): RedirectResponse
    {
        return redirect()->route('admin.departments.index', ['edit' => $department->id]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $this->departmentService->update($department, $request->validated());

        return redirect()
            ->route('admin.departments.index')
            ->with('success', "Bo'lim muvaffaqiyatli yangilandi.");
    }

    public function destroy(Department $department): RedirectResponse
    {
        if ($error = $this->departmentService->delete($department)) {
            return redirect()->back()->with('error', $error);
        }

        return redirect()
            ->route('admin.departments.index')
            ->with('success', "Bo'lim muvaffaqiyatli o'chirildi.");
    }
}
