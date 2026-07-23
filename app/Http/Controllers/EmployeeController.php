<?php

namespace App\Http\Controllers;

use App\Http\Requests\RemoveEmployeeDevicesRequest;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Services\EmployeeService;
use App\Services\Hikvision\EmployeeSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employeeService,
        private readonly EmployeeSyncService $employeeSyncService,
    ) {}

    public function index(Request $request): Response
    {
        $employees = $this->employeeService->paginate(12);

        return Inertia::render('Admin/Employees/Index', [
            'employees' => $employees->through(
                fn (Employee $employee): array => EmployeeResource::make($employee)->toArray($request),
            ),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Employees/Form', [
            'departments' => $this->employeeService->getDepartmentsForForm()->map->only(['id', 'name'])->values(),
            'schedules' => $this->employeeService->getSchedulesForForm()->map->only(['id', 'name'])->values(),
        ]);
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $this->employeeService->create($request->validated());

        return redirect()
            ->route('admin.employees.index')
            ->with('success', 'Xodim muvaffaqiyatli yaratildi. Qurilmaga yuborish uchun Sinxronizatsiya sahifasidan foydalaning.');
    }

    public function show(Request $request, Employee $employee): Response
    {
        $employee = $this->employeeService->loadForShow($employee);

        return Inertia::render('Admin/Employees/Show', [
            'employee' => EmployeeResource::make($employee)->toArray($request),
        ]);
    }

    public function removeDevices(RemoveEmployeeDevicesRequest $request, Employee $employee): RedirectResponse
    {
        $this->employeeSyncService->queueRemoveFromDevices(
            $employee,
            $request->validated('device_ids'),
        );

        return redirect()
            ->route('admin.employees.show', $employee)
            ->with('success', 'Xodimni qurilmalardan o‘chirish navbatga qo‘yildi.');
    }

    public function edit(Request $request, Employee $employee): Response
    {
        $employee = $this->employeeService->loadForEdit($employee);

        return Inertia::render('Admin/Employees/Form', [
            'employee' => EmployeeResource::make($employee)->toArray($request),
            'departments' => $this->employeeService->getDepartmentsForForm()->map->only(['id', 'name'])->values(),
            'schedules' => $this->employeeService->getSchedulesForForm()->map->only(['id', 'name'])->values(),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $data = $request->validated();
        $data['remove_face'] = $request->boolean('remove_face');

        $this->employeeService->update($employee, $data);

        return redirect()
            ->route('admin.employees.index')
            ->with('success', 'Xodim muvaffaqiyatli yangilandi. O‘zgarishni qurilmaga yuborish uchun Sinxronizatsiya sahifasidan foydalaning.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $this->employeeService->delete($employee);

        return redirect()
            ->route('admin.employees.index')
            ->with('success', 'Xodim o‘chirildi. Qurilmalardan olib tashlash navbatga qo‘yildi.');
    }
}
