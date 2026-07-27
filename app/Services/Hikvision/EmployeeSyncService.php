<?php

namespace App\Services\Hikvision;

use App\Jobs\Hikvision\ClearDeviceUsersJob;
use App\Jobs\Hikvision\RemoveHikvisionFacesJob;
use App\Jobs\Hikvision\RemoveHikvisionUsersJob;
use App\Jobs\Hikvision\SyncDeviceEmployeesJob;
use App\Jobs\Hikvision\SyncEmployeeJob;
use App\Jobs\Hikvision\TransferEmployeesJob;
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
     */
    public function queueTransfer(array $deviceIds, array $employeeIds): void
    {
        TransferEmployeesJob::dispatch($deviceIds, $employeeIds);
    }

    public function queueSyncEmployee(Employee $employee, ?Device $device = null): void
    {
        SyncEmployeeJob::dispatch(
            $employee->id,
            $device !== null ? [$device->id] : null,
        );
    }

    public function queueSyncAllEmployees(Device $device): void
    {
        SyncDeviceEmployeesJob::dispatch($device->id);
    }

    /**
     * @param  list<int>  $deviceIds
     */
    public function queueRemoveFromDevices(Employee $employee, array $deviceIds): void
    {
        if ($deviceIds === []) {
            return;
        }

        RemoveHikvisionUsersJob::dispatch($employee->card, $deviceIds, $employee->id);
    }

    public function queueRemoveEmployee(Employee $employee, ?Device $device = null): void
    {
        $deviceIds = $device !== null
            ? [$device->id]
            : $employee->devices()->pluck('devices.id')->all();

        if ($deviceIds === []) {
            return;
        }

        // Xodim DB dan o‘chirilgach ham ishlashi uchun faqat card + device ids.
        RemoveHikvisionUsersJob::dispatch($employee->card, $deviceIds);
    }

    public function queueRemoveFace(Employee $employee, ?Device $device = null): void
    {
        $deviceIds = $this->resolveDevices($employee, $device)->pluck('id')->all();

        if ($deviceIds === []) {
            return;
        }

        RemoveHikvisionFacesJob::dispatch($employee->card, $deviceIds);
    }

    public function queueClearDeviceUsers(Device $device): void
    {
        ClearDeviceUsersJob::dispatch($device->id);
    }

    /**
     * @param  list<int>  $deviceIds
     * @param  list<int>  $employeeIds
     * @return array{success:int, failed:int, skipped:int}
     */
    public function transfer(array $deviceIds, array $employeeIds): array
    {
        $devices = Device::query()->whereIn('id', $deviceIds)->get()
            ->filter(fn (Device $device): bool => $device->hasIsapiCredentials())
            ->values();
        $employees = Employee::query()->with('face')->whereIn('id', $employeeIds)->get();

        $success = 0;
        $failed = 0;
        $skipped = 0;

        if ($devices->isEmpty() || $employees->isEmpty()) {
            return [
                'success' => 0,
                'failed' => 0,
                'skipped' => max($employees->count(), 1),
            ];
        }

        $onlineDevices = $devices->filter(fn (Device $device): bool => $device->is_online)->values();
        $offlineDevices = $devices->filter(fn (Device $device): bool => ! $device->is_online)->values();

        // Online bor bo‘lsa faqat ular — offline timeoutdan saqlaydi.
        // Hammasi offline bo‘lsa (eski holat) baribir uriniladi.
        if ($onlineDevices->isNotEmpty()) {
            $targetDevices = $onlineDevices;
            $skipped = $offlineDevices->count() * $employees->count();
        } else {
            $targetDevices = $devices;
        }

        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        foreach ($employees as $employee) {
            foreach ($targetDevices as $device) {
                $synced = $this->pushToDevice($employee, $device);
                $synced ? $success++ : $failed++;
            }
        }

        Log::info('Hikvision: sinxron yuborish yakunlandi', [
            'devices' => $deviceIds,
            'employees' => $employeeIds,
            'success' => $success,
            'failed' => $failed,
            'skipped' => $skipped,
        ]);

        return ['success' => $success, 'failed' => $failed, 'skipped' => $skipped];
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
     * Xodim DB dan o‘chirilgandan keyin qurilmadan o‘chirish.
     *
     * @param  list<int>  $deviceIds
     * @return array{success:int, failed:int}
     */
    public function removeCardFromDevices(string $employeeCard, array $deviceIds): array
    {
        $devices = Device::query()->whereIn('id', $deviceIds)->get();
        $success = 0;
        $failed = 0;

        foreach ($devices as $device) {
            try {
                if ($this->userService->deleteUser($device, $employeeCard)) {
                    $success++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $exception) {
                $failed++;
                Log::error('Hikvision: xodim (card) qurilmadan o‘chirish xatoligi', [
                    'device_id' => $device->id,
                    'employee_card' => $employeeCard,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return ['success' => $success, 'failed' => $failed];
    }

    /**
     * Sync only employees already linked to this device (HTTP request ichida).
     *
     * @return array{success:int, failed:int}
     */
    public function syncAllEmployees(Device $device): array
    {
        $success = 0;
        $failed = 0;

        if (! $device->hasIsapiCredentials()) {
            return ['success' => 0, 'failed' => 0];
        }

        // Ko‘p xodim + ISAPI chaqiriqlari PHP limitidan oshishi mumkin.
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        $device->employees()
            ->with('face')
            ->orderBy('employees.id')
            ->chunkById(50, function ($employees) use ($device, &$success, &$failed): void {
                foreach ($employees as $employee) {
                    if ($this->pushToDevice($employee, $device)) {
                        $success++;
                    } else {
                        $failed++;
                    }
                }
            }, 'employees.id', 'id');

        Log::info('Hikvision: qurilmaga ulangan xodimlar sync qilindi', [
            'device_id' => $device->id,
            'success' => $success,
            'failed' => $failed,
        ]);

        return ['success' => $success, 'failed' => $failed];
    }

    public function removeFace(Employee $employee, ?Device $device = null): void
    {
        $this->removeCardFacesFromDevices(
            $employee->card,
            $this->resolveDevices($employee, $device)->pluck('id')->all(),
        );
    }

    /**
     * @param  list<int>  $deviceIds
     */
    public function removeCardFacesFromDevices(string $employeeCard, array $deviceIds): void
    {
        $devices = Device::query()->whereIn('id', $deviceIds)->get();

        foreach ($devices as $device) {
            try {
                $this->userService->deleteFace($device, $employeeCard);
            } catch (\Throwable $exception) {
                Log::error('Hikvision: yuz o‘chirish sync xatoligi', [
                    'device_id' => $device->id,
                    'employee_card' => $employeeCard,
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
            try {
                if ($this->userService->deleteUser($device, $employee->card)) {
                    $device->employees()->detach($employee->id);
                    $success++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $exception) {
                $failed++;
                Log::error('Hikvision: qurilmani tozalash xatoligi', [
                    'device_id' => $device->id,
                    'employee_id' => $employee->id,
                    'message' => $exception->getMessage(),
                ]);
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
     * Faqat aniq berilgan yoki allaqachon bog‘langan qurilmalar.
     * Yangi xodim avtomatik qurilmaga yuborilmaydi — Sync sahifasi kerak.
     *
     * @return Collection<int, Device>
     */
    public function resolveDevices(Employee $employee, ?Device $device = null): Collection
    {
        if ($device !== null) {
            return collect([$device]);
        }

        return $employee->devices()->get();
    }
}
