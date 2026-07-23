<?php

namespace App\Jobs\Hikvision;

use App\Services\Hikvision\EmployeeSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TransferEmployeesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  list<int>  $deviceIds
     * @param  list<int>  $employeeIds
     */
    public function __construct(
        public readonly array $deviceIds,
        public readonly array $employeeIds,
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
        $employeeSyncService->transfer($this->deviceIds, $this->employeeIds);
    }
}
