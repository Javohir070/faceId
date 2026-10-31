<?php

namespace App\Http\Controllers;

use App\Exports\EmployeesExport;
use App\Http\Requests\BulkDestroyEmployeesRequest;
use App\Http\Requests\PullEmployeesRequest;
use App\Http\Requests\RemoveEmployeeDevicesRequest;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Category;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Note;
use App\Services\Employees\EmployeeProfileService;
use App\Services\EmployeeService;
use App\Services\Hikvision\EmployeePullService;
use App\Services\Hikvision\EmployeeSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employeeService,
        private readonly EmployeeSyncService $employeeSyncService,
        private readonly EmployeePullService $employeePullService,
        private readonly EmployeeProfileService $profileService,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate($this->filterRules());

        $employees = $this->employeeService->paginate(12, $filters);

        return Inertia::render('Admin/Employees/Index', [
            'employees' => $employees->through(
                fn (Employee $employee): array => EmployeeResource::make($employee)->toArray($request),
            ),
            'filters' => [
                'search' => $filters['search'] ?? null,
                'department_ids' => array_values(array_map('intval', $filters['department_ids'] ?? [])),
                'group_ids' => array_values(array_map('intval', $filters['group_ids'] ?? [])),
                'schedule_id' => isset($filters['schedule_id']) ? (int) $filters['schedule_id'] : null,
                'device_id' => isset($filters['device_id']) ? (int) $filters['device_id'] : null,
                'has_face' => $filters['has_face'] ?? null,
            ],
            'departments' => $this->employeeService->getDepartmentsForForm()->map->only(['id', 'name'])->values(),
            'groups' => $this->employeeService->getGroupsForForm()->map->only(['id', 'name', 'department_id', 'parent_id'])->values(),
            'schedules' => $this->employeeService->getSchedulesForForm()->map->only(['id', 'name'])->values(),
            'devices' => Device::query()->ordered()->get(['id', 'name']),
        ]);
    }

    public function pull(): Response
    {
        $devices = Device::query()
            ->with('category:id,name')
            ->ordered()
            ->get()
            ->map(fn (Device $device): array => [
                'id' => $device->id,
                'name' => $device->name,
                'ip_address' => $device->ip_address,
                'type_label' => $device->typeLabel(),
                'category' => $device->category?->only(['id', 'name']),
                'is_online' => $device->is_online,
                'has_credentials' => $device->hasIsapiCredentials(),
            ]);

        return Inertia::render('Admin/Employees/Pull', [
            'devices' => $devices,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function pullStore(PullEmployeesRequest $request): RedirectResponse
    {
        $result = $this->employeePullService->pullDevices($request->validated('device_ids'));

        $message = sprintf(
            'Xodimlar tortildi: %d yangi, %d yangilangan, %d bog‘langan, %d o‘tkazildi, %d yuz, %d qurilma xato.',
            $result['created'],
            $result['updated'],
            $result['linked'],
            $result['skipped'],
            $result['faces'],
            $result['failed'],
        );

        if ($result['created'] + $result['updated'] + $result['linked'] + $result['faces'] === 0
            && $result['failed'] > 0) {
            return redirect()
                ->route('admin.employees.pull')
                ->with('error', $message.' Qurilma ulanishi yoki login/parolni tekshiring.');
        }

        if ($result['failed'] > 0) {
            return redirect()
                ->route('admin.employees.index')
                ->with('error', $message);
        }

        return redirect()
            ->route('admin.employees.index')
            ->with('success', $message);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Employees/Form', [
            'suggestedCard' => Employee::generateUniqueCard(),
            'departments' => $this->employeeService->getDepartmentsForForm()->map->only(['id', 'name'])->values(),
            'schedules' => $this->employeeService->getSchedulesForForm()->map->only(['id', 'name'])->values(),
            'groups' => $this->employeeService->getGroupsForForm()->map->only(['id', 'name', 'department_id'])->values(),
            'countries' => collect(config('countries'))->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values(),
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
        $filters = $request->validate($this->showFilterRules());
        $now = now();
        $month = CarbonImmutable::createFromFormat('!Y-m', $filters['month'] ?? $now->format('Y-m'));

        // Kirish-chiqishlar oralig‘i: tanlangan sanalar, aks holda kalendardagi oy.
        $from = $filters['from'] ?? $month->startOfMonth()->toDateString();
        $to = $filters['to'] ?? $month->endOfMonth()->toDateString();
        $passFilters = [
            'from' => min($from, $to),
            'to' => max($from, $to),
            'type' => $filters['type'] ?? null,
            'device_id' => isset($filters['device_id']) ? (int) $filters['device_id'] : null,
        ];

        $employee = $this->employeeService->loadForShow($employee);
        $employee->load([
            'notes' => fn ($query) => $query->orderByDesc('dt')->orderByDesc('id'),
        ]);

        return Inertia::render('Admin/Employees/Show', [
            'employee' => EmployeeResource::make($employee)->toArray($request),
            'notes' => $employee->notes->map(fn (Note $note): array => [
                'id' => $note->id,
                'type' => $note->type->value,
                'type_label' => $note->type->label(),
                'dt' => $note->dt->format('Y-m-d'),
                'note' => $note->note,
            ])->values(),
            'filters' => [...$passFilters, 'month' => $month->format('Y-m')],
            'month' => fn () => $this->profileService->month($employee, $month, $now),
            'passes' => fn () => $this->profileService->passes($employee, $passFilters),
            'passes_summary' => fn () => $this->profileService->passesSummary($employee, $passFilters),
            'pass_devices' => fn () => $this->profileService->passDevices($employee),
            'day' => fn () => isset($filters['day'])
                ? $this->profileService->day($employee, $filters['day'], $now)
                : null,
        ]);
    }

    public function removeDevices(RemoveEmployeeDevicesRequest $request, Employee $employee): RedirectResponse
    {
        $deviceIds = $request->validated('device_ids');
        $result = $this->employeeSyncService->removeFromDevices($employee, $deviceIds);

        $redirect = redirect()->back(fallback: route('admin.employees.show', $employee));

        if ($result['success'] === 0 && $result['failed'] === 0) {
            return $redirect->with('error', 'Tanlangan qurilma topilmadi.');
        }

        if ($result['failed'] > 0 && $result['success'] === 0) {
            return $redirect->with('error', 'Qurilmalardan o‘chirib bo‘lmadi. Qurilma ulanishi yoki login/parolni tekshiring.');
        }

        if ($result['failed'] > 0) {
            return $redirect->with('error', sprintf(
                'Qisman o‘chirildi: %d muvaffaqiyatli, %d xato.',
                $result['success'],
                $result['failed'],
            ));
        }

        return $redirect->with('success', 'Xodim tanlangan qurilmalardan o‘chirildi.');
    }

    public function edit(Request $request, Employee $employee): Response
    {
        $employee = $this->employeeService->loadForEdit($employee);

        return Inertia::render('Admin/Employees/Form', [
            'employee' => EmployeeResource::make($employee)->toArray($request),
            'departments' => $this->employeeService->getDepartmentsForForm()->map->only(['id', 'name'])->values(),
            'schedules' => $this->employeeService->getSchedulesForForm()->map->only(['id', 'name'])->values(),
            'groups' => $this->employeeService->getGroupsForForm()->map->only(['id', 'name', 'department_id'])->values(),
            'countries' => collect(config('countries'))->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values(),
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

    public function bulkDestroy(BulkDestroyEmployeesRequest $request): RedirectResponse
    {
        $deleted = $this->employeeService->deleteMany($request->validated('employee_ids'));

        return redirect()
            ->back(fallback: route('admin.employees.index'))
            ->with('success', $deleted.' ta xodim o‘chirildi. Qurilmalardan olib tashlash navbatga qo‘yildi.');
    }

    public function export(Request $request): BinaryFileResponse
    {
        $filters = $request->validate($this->filterRules());

        $query = $this->employeeService->getFilteredQuery($filters);

        return Excel::download(new EmployeesExport($query), 'xodimlar.xlsx');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    /**
     * @return array<string, array<int, string>>
     */
    private function showFilterRules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'type' => ['nullable', 'in:entry,exit'],
            'device_id' => ['nullable', 'integer'],
            'day' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    private function filterRules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => ['integer', 'exists:groups,id'],
            'schedule_id' => ['nullable', 'integer', 'exists:schedules,id'],
            'device_id' => ['nullable', 'integer', 'exists:devices,id'],
            'has_face' => ['nullable', 'in:0,1'],
        ];
    }
}
