<?php

namespace App\Jobs\Hikvision;

use App\Models\Employee;
use App\Services\Hikvision\EmployeeSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RemoveHikvisionUsersJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  list<int>  $deviceIds
     */
    public function __construct(
        public readonly string $employeeCard,
        public readonly array $deviceIds,
        public readonly ?int $employeeId = null,
    ) {
        $this->afterCommit();
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
        $employee = $this->employeeId !== null
            ? Employee::query()->find($this->employeeId)
            : null;

        if ($employee !== null) {
            $employeeSyncService->removeFromDevices($employee, $this->deviceIds);

            return;
        }

        $employeeSyncService->removeCardFromDevices($this->employeeCard, $this->deviceIds);
    }
}
