<?php

namespace App\Jobs\Hikvision;

use App\Services\Hikvision\EmployeeSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RemoveHikvisionFacesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  list<int>  $deviceIds
     */
    public function __construct(
        public readonly string $employeeCard,
        public readonly array $deviceIds,
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
        $employeeSyncService->removeCardFacesFromDevices($this->employeeCard, $this->deviceIds);
    }
}
