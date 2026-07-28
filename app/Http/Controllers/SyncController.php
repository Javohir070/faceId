<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransferSyncRequest;
use App\Models\Category;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Services\Hikvision\EmployeeSyncService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SyncController extends Controller
{
    public function __construct(
        private readonly EmployeeSyncService $employeeSyncService,
    ) {}

    public function index(): Response
    {
        $devices = Device::query()
            ->with('category:id,name')
            ->withCount([
                'employees as assigned_employees_count',
                'employees as synced_employees_count' => fn ($query) => $query->whereNotNull('device_employee.synced_at'),
            ])
            ->ordered()
            ->get()
            ->map(fn (Device $device): array => [
                'id' => $device->id,
                'name' => $device->name,
                'ip_address' => $device->ip_address,
                'port' => $device->port,
                'type' => $device->type,
                'type_label' => $device->typeLabel(),
                'category' => $device->category?->only(['id', 'name']),
                'is_online' => $device->is_online,
                'assigned_employees_count' => $device->assigned_employees_count,
                'synced_employees_count' => $device->synced_employees_count,
            ]);

        $employees = Employee::query()
            ->with('department:id,name')
            ->withExists('face')
            ->withCount([
                'devices as assigned_devices_count',
                'devices as synced_devices_count' => fn ($query) => $query->whereNotNull('device_employee.synced_at'),
            ])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->name,
                'card' => $employee->card,
                'department' => $employee->department?->only(['id', 'name']),
                'assigned_devices_count' => $employee->assigned_devices_count,
                'synced_devices_count' => $employee->synced_devices_count,
                'has_face' => (bool) $employee->face_exists,
            ]);

        return Inertia::render('Admin/Sync/Index', [
            'devices' => $devices,
            'employees' => $employees,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function transfer(TransferSyncRequest $request): RedirectResponse
    {
        $result = $this->employeeSyncService->transfer(
            $request->validated('device_ids'),
            $request->validated('employee_ids'),
        );

        $parts = [
            sprintf('%d muvaffaqiyatli', $result['success']),
            sprintf('%d xato', $result['failed']),
        ];

        if (($result['skipped'] ?? 0) > 0) {
            $parts[] = sprintf('%d o‘tkazib yuborildi (ulanmagan)', $result['skipped']);
        }

        $message = 'Sinxronizatsiya yakunlandi: '.implode(', ', $parts).'.';

        if ($result['success'] === 0 && $result['failed'] === 0 && ($result['skipped'] ?? 0) > 0) {
            return redirect()
                ->route('admin.sync.index')
                ->with('error', $message.' Avval qurilmani ulab yoki ping qiling.');
        }

        if ($result['failed'] > 0 && $result['success'] === 0) {
            return redirect()
                ->route('admin.sync.index')
                ->with('error', $message.' Qurilma ulanishi, login/parol yoki yuz rasmini tekshiring.');
        }

        if ($result['failed'] > 0 || ($result['skipped'] ?? 0) > 0) {
            return redirect()
                ->route('admin.sync.index')
                ->with('error', $message);
        }

        return redirect()
            ->route('admin.sync.index')
            ->with('success', $message);
    }
}
