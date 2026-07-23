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
            ->orderBy('name')
            ->get()
            ->map(fn (Device $device): array => [
                'id' => $device->id,
                'name' => $device->name,
                'ip_address' => $device->ip_address,
                'category' => $device->category?->only(['id', 'name']),
                'is_online' => $device->is_online,
            ]);

        $employees = Employee::query()
            ->with('department:id,name')
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
        $this->employeeSyncService->queueTransfer(
            $request->validated('device_ids'),
            $request->validated('employee_ids'),
        );

        return redirect()
            ->route('admin.sync.index')
            ->with('success', 'Xodimlarni qurilmalarga yuborish navbatga qo‘yildi. Natijani Sync holati va loglardan kuzating.');
    }
}
