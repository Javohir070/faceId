<?php

namespace App\Jobs\Hikvision;

use App\Models\Device;
use App\Services\Hikvision\EmployeeSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncDeviceEmployeesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $deviceId,
    ) {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return "sync-device-employees-{$this->deviceId}";
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [15, 45, 90];
    }

    public function handle(EmployeeSyncService $employeeSyncService): void
    {
        $device = Device::query()->find($this->deviceId);

        if ($device === null) {
            return;
        }

        $employeeSyncService->syncAllEmployees($device);
    }
}
