<?php

namespace App\Http\Controllers;

use App\Http\Requests\RemoveEmployeeDevicesRequest;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Services\EmployeeService;
use App\Services\Hikvision\EmployeeSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employeeService,
        private readonly EmployeeSyncService $employeeSyncService,
    ) {}

    public function index(): Response
    {
        $employees = $this->employeeService->paginate(12);

        $employees->getCollection()->transform(fn (Employee $employee): array => [
            'id' => $employee->id,
            'name' => $employee->name,
            'card' => $employee->card,
            'has_face' => $employee->face !== null,
            'valid_from_dt' => $employee->valid_from_dt?->format('Y-m-d H:i'),
            'valid_to_dt' => $employee->valid_to_dt?->format('Y-m-d H:i'),
            'department' => $employee->department?->only(['id', 'name']),
            'schedule' => $employee->schedule?->only(['id', 'name']),
        ]);

        return Inertia::render('Admin/Employees/Index', [
            'employees' => $employees,
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
            ->with('success', 'Xodim muvaffaqiyatli yaratildi.');
    }

    public function show(Employee $employee): Response
    {
        $employee = $this->employeeService->loadForShow($employee);

        return Inertia::render('Admin/Employees/Show', [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'card' => $employee->card,
                'valid_from_dt' => $employee->valid_from_dt?->format('Y-m-d H:i'),
                'valid_to_dt' => $employee->valid_to_dt?->format('Y-m-d H:i'),
                'created_at' => $employee->created_at,
                'department' => $employee->department?->only(['id', 'name']),
                'schedule' => $employee->schedule?->only(['id', 'name']),
                'face_preview' => $this->facePreviewUrl($employee->face?->face),
                'devices' => $employee->devices
                    ->sortBy('name')
                    ->values()
                    ->map(fn ($device): array => [
                        'id' => $device->id,
                        'name' => $device->name,
                        'ip_address' => $device->ip_address,
                        'is_synced' => filled($device->pivot->synced_at),
                        'synced_at' => filled($device->pivot->synced_at)
                            ? Carbon::parse($device->pivot->synced_at)->format('Y-m-d H:i')
                            : null,
                    ]),
            ],
        ]);
    }

    public function removeDevices(RemoveEmployeeDevicesRequest $request, Employee $employee): RedirectResponse
    {
        $result = $this->employeeSyncService->removeFromDevices(
            $employee,
            $request->validated('device_ids'),
        );

        return redirect()
            ->route('admin.employees.show', $employee)
            ->with('success', sprintf(
                'Qurilmalardan o‘chirildi: %d muvaffaqiyatli, %d xatolik.',
                $result['success'],
                $result['failed'],
            ));
    }

    public function edit(Employee $employee): Response
    {
        $employee = $this->employeeService->loadForEdit($employee);

        return Inertia::render('Admin/Employees/Form', [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'card' => $employee->card,
                'schedule_id' => $employee->schedule_id,
                'department_id' => $employee->department_id,
                'valid_from_dt' => $employee->valid_from_dt?->format('Y-m-d\TH:i'),
                'valid_to_dt' => $employee->valid_to_dt?->format('Y-m-d\TH:i'),
                'face_preview' => $this->facePreviewUrl($employee->face?->face),
            ],
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
            ->with('success', 'Xodim muvaffaqiyatli yangilandi.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $this->employeeService->delete($employee);

        return redirect()
            ->route('admin.employees.index')
            ->with('success', "Xodim muvaffaqiyatli o'chirildi.");
    }

    private function facePreviewUrl(?string $face): ?string
    {
        if ($face === null || $face === '') {
            return null;
        }

        if (str_starts_with($face, 'data:')) {
            return $face;
        }

        return 'data:image/jpeg;base64,'.$face;
    }
}
