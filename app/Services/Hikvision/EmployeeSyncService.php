<?php

namespace App\Services\Hikvision;

use App\Models\Device;
use App\Models\Employee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class EmployeeSyncService
{
    public function __construct(
        private readonly HikvisionUserService $userService,
    ) {}

    /**
     * @param  list<int>  $deviceIds
     * @param  list<int>  $employeeIds
     * @return array{success:int, failed:int}
     */
    public function transfer(array $deviceIds, array $employeeIds): array
    {
        $devices = Device::query()->whereIn('id', $deviceIds)->get();
        $employees = Employee::query()->with('face')->whereIn('id', $employeeIds)->get();

        $success = 0;
        $failed = 0;

        foreach ($employees as $employee) {
            foreach ($devices as $device) {
                $synced = $this->pushToDevice($employee, $device);
                $synced ? $success++ : $failed++;
            }
        }

        Log::info('Hikvision: sinxron yuborish yakunlandi', [
            'devices' => $deviceIds,
            'employees' => $employeeIds,
            'success' => $success,
            'failed' => $failed,
        ]);

        return ['success' => $success, 'failed' => $failed];
    }

    public function syncEmployee(Employee $employee, ?Device $device = null): void
    {
        foreach ($this->resolveDevices($employee, $device) as $target) {
            $this->pushToDevice($employee, $target);
        }
    }

    public function removeEmployee(Employee $employee, ?Device $device = null): void
    {
        if ($device !== null) {
            $this->removeFromDevices($employee, [$device->id]);

            return;
        }

        $this->removeFromDevices($employee, $employee->devices()->pluck('devices.id')->all());
    }

    /**
     * @param  list<int>  $deviceIds
     * @return array{success:int, failed:int}
     */
    public function removeFromDevices(Employee $employee, array $deviceIds): array
    {
        $devices = Device::query()->whereIn('id', $deviceIds)->get();
        $success = 0;
        $failed = 0;

        foreach ($devices as $device) {
            try {
                if ($this->userService->deleteUser($device, $employee->card)) {
                    $employee->devices()->detach($device->id);
                    $success++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $exception) {
                $failed++;
                Log::error('Hikvision: xodim qurilmadan o‘chirish xatoligi', [
                    'device_id' => $device->id,
                    'employee_id' => $employee->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return ['success' => $success, 'failed' => $failed];
    }

    /**
     * @return array{success:int, failed:int}
     */
    public function syncAllEmployees(Device $device): array
    {
        $success = 0;
        $failed = 0;

        Employee::query()
            ->with('face')
            ->orderBy('id')
            ->chunkById(50, function ($employees) use ($device, &$success, &$failed): void {
                foreach ($employees as $employee) {
                    if ($this->pushToDevice($employee, $device)) {
                        $success++;
                    } else {
                        $failed++;
                    }
                }
            });

        Log::info('Hikvision: barcha xodimlar sync qilindi', [
            'device_id' => $device->id,
            'success' => $success,
            'failed' => $failed,
        ]);

        return ['success' => $success, 'failed' => $failed];
    }

    public function removeFace(Employee $employee, ?Device $device = null): void
    {
        foreach ($this->resolveDevices($employee, $device) as $target) {
            try {
                $this->userService->deleteFace($target, $employee->card);
            } catch (\Throwable $exception) {
                Log::error('Hikvision: yuz o‘chirish sync xatoligi', [
                    'device_id' => $target->id,
                    'employee_id' => $employee->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return array{success:int, failed:int}
     */
    public function clearDeviceUsers(Device $device): array
    {
        $success = 0;
        $failed = 0;

        foreach ($device->employees()->get() as $employee) {
            if ($this->userService->deleteUser($device, $employee->card)) {
                $device->employees()->detach($employee->id);
                $success++;
            } else {
                $failed++;
            }
        }

        return ['success' => $success, 'failed' => $failed];
    }

    private function pushToDevice(Employee $employee, Device $device): bool
    {
        try {
            $synced = $this->userService->addOrUpdateUser($device, $employee);

            $employee->devices()->syncWithoutDetaching([
                $device->id => [
                    'synced_at' => $synced ? now() : null,
                    'sync_error' => $synced ? null : 'ISAPI javob bermadi',
                ],
            ]);

            if (! $synced) {
                Log::error('Hikvision: xodim sync xatoligi', [
                    'device_id' => $device->id,
                    'employee_id' => $employee->id,
                ]);
            }

            return $synced;
        } catch (\Throwable $exception) {
            $employee->devices()->syncWithoutDetaching([
                $device->id => [
                    'synced_at' => null,
                    'sync_error' => $exception->getMessage(),
                ],
            ]);

            Log::error('Hikvision: xodim sync xatoligi', [
                'device_id' => $device->id,
                'employee_id' => $employee->id,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @return Collection<int, Device>
     */
    private function resolveDevices(Employee $employee, ?Device $device = null): Collection
    {
        if ($device !== null) {
            return collect([$device]);
        }

        return $employee->devices()->get();
    }
}
