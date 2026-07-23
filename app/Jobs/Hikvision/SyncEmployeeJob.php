<?php

namespace App\Jobs\Hikvision;

use App\Models\Device;
use App\Models\Employee;
use App\Services\Hikvision\EmployeeSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncEmployeeJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 120;

    /**
     * @param  list<int>|null  $deviceIds
     */
    public function __construct(
        public readonly int $employeeId,
        public readonly ?array $deviceIds = null,
    ) {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        $devices = $this->deviceIds === null
            ? 'auto'
            : implode('-', $this->deviceIds);

        return "sync-employee-{$this->employeeId}-{$devices}";
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(EmployeeSyncService $employeeSyncService): void
    {
        $employee = Employee::query()->with('face')->find($this->employeeId);

        if ($employee === null) {
            return;
        }

        if ($this->deviceIds === null) {
            $employeeSyncService->syncEmployee($employee);

            return;
        }

        foreach (Device::query()->whereIn('id', $this->deviceIds)->get() as $device) {
            $employeeSyncService->syncEmployee($employee, $device);
        }
    }
}
