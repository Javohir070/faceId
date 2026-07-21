<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\Department;
use App\Services\DepartmentService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function __construct(
        private readonly DepartmentService $departmentService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Departments/Index', [
            'departments' => $this->departmentService->paginate(10),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Departments/Form');
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $this->departmentService->create($request->validated());

        return redirect()
            ->route('admin.departments.index')
            ->with('success', "Bo'lim muvaffaqiyatli yaratildi.");
    }

    public function show(Department $department): Response
    {
        return Inertia::render('Admin/Departments/Show', [
            'department' => [
                'id' => $department->id,
                'name' => $department->name,
                'created_at' => $department->created_at,
            ],
        ]);
    }

    public function edit(Department $department): Response
    {
        return Inertia::render('Admin/Departments/Form', [
            'department' => $department->only(['id', 'name']),
        ]);
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
        $this->departmentService->delete($department);

        return redirect()
            ->route('admin.departments.index')
            ->with('success', "Bo'lim muvaffaqiyatli o'chirildi.");
    }
}
