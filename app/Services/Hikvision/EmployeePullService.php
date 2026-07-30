<?php

namespace App\Services\Hikvision;

use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Face;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmployeePullService
{
    public function __construct(
        private readonly HikvisionUserService $userService,
    ) {}

    /**
     * @return array{created:int, updated:int, linked:int, skipped:int, failed:int, faces:int}
     */
    public function pullAllDevices(): array
    {
        $created = 0;
        $updated = 0;
        $linked = 0;
        $skipped = 0;
        $failed = 0;
        $faces = 0;

        Device::query()
            ->where('is_online', true)
            ->orderBy('id')
            ->each(function (Device $device) use (&$created, &$updated, &$linked, &$skipped, &$failed, &$faces): void {
                if (! $device->hasIsapiCredentials()) {
                    $failed++;

                    return;
                }

                try {
                    $result = $this->pullDevice($device);
                    $created += $result['created'];
                    $updated += $result['updated'];
                    $linked += $result['linked'];
                    $skipped += $result['skipped'];
                    $faces += $result['faces'];
                } catch (\Throwable $exception) {
                    $failed++;
                    Log::warning('Hikvision: xodimlarni tortib olishda xatolik', [
                        'device_id' => $device->id,
                        'message' => $exception->getMessage(),
                    ]);
                }
            });

        if ($created + $updated + $linked + $faces > 0) {
            Log::info('Hikvision: xodimlar tortib olindi', compact(
                'created',
                'updated',
                'linked',
                'skipped',
                'failed',
                'faces',
            ));
        }

        return compact('created', 'updated', 'linked', 'skipped', 'failed', 'faces');
    }

    /**
     * @param  list<int>  $deviceIds
     * @return array{created:int, updated:int, linked:int, skipped:int, failed:int, faces:int, devices:int}
     */
    public function pullDevices(array $deviceIds): array
    {
        $created = 0;
        $updated = 0;
        $linked = 0;
        $skipped = 0;
        $failed = 0;
        $faces = 0;
        $devices = 0;

        Device::query()
            ->whereIn('id', $deviceIds)
            ->orderBy('id')
            ->each(function (Device $device) use (&$created, &$updated, &$linked, &$skipped, &$failed, &$faces, &$devices): void {
                if (! $device->hasIsapiCredentials()) {
                    $failed++;

                    return;
                }

                $devices++;

                try {
                    $result = $this->pullDevice($device);
                    $created += $result['created'];
                    $updated += $result['updated'];
                    $linked += $result['linked'];
                    $skipped += $result['skipped'];
                    $faces += $result['faces'];
                } catch (\Throwable $exception) {
                    $failed++;
                    Log::warning('Hikvision: tanlangan qurilmadan xodim tortishda xatolik', [
                        'device_id' => $device->id,
                        'message' => $exception->getMessage(),
                    ]);
                }
            });

        Log::info('Hikvision: qo‘lda xodimlar tortib olindi', compact(
            'devices',
            'created',
            'updated',
            'linked',
            'skipped',
            'failed',
            'faces',
        ));

        return compact('created', 'updated', 'linked', 'skipped', 'failed', 'faces', 'devices');
    }

    /**
     * @return array{created:int, updated:int, linked:int, skipped:int, failed:int, faces:int}
     */
    public function pullDevice(Device $device): array
    {
        $created = 0;
        $updated = 0;
        $linked = 0;
        $skipped = 0;
        $failed = 0;
        $faces = 0;

        if (! $device->hasIsapiCredentials()) {
            return compact('created', 'updated', 'linked', 'skipped', 'failed', 'faces');
        }

        $users = $this->userService->fetchAllUsers($device);
        $department = $this->resolveImportDepartment();
        $schedule = $this->resolveImportSchedule();

        foreach ($users as $user) {
            $employeeNo = trim((string) ($user['employeeNo'] ?? ''));

            if ($employeeNo === '') {
                $skipped++;

                continue;
            }

            try {
                $result = DB::transaction(function () use ($device, $user, $employeeNo, $department, $schedule): array {
                    return $this->upsertAndLink($device, $user, $employeeNo, $department, $schedule);
                });

                match ($result['outcome']) {
                    'created' => $created++,
                    'updated' => $updated++,
                    'linked' => $linked++,
                    'skipped' => $skipped++,
                    default => $skipped++,
                };

                if ($result['face_saved']) {
                    $faces++;
                }
            } catch (\Throwable $exception) {
                $failed++;
                Log::warning('Hikvision: xodim import xatoligi', [
                    'device_id' => $device->id,
                    'employee_no' => $employeeNo,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return compact('created', 'updated', 'linked', 'skipped', 'failed', 'faces');
    }

    /**
     * @param  array{employeeNo:string, name:string, beginTime:?string, endTime:?string, picURL?:?string}  $user
     * @return array{outcome:'created'|'updated'|'linked'|'skipped', face_saved:bool}
     */
    private function upsertAndLink(
        Device $device,
        array $user,
        string $employeeNo,
        Department $department,
        Schedule $schedule,
    ): array {
        $existing = Employee::query()->where('card', $employeeNo)->first();
        $name = trim((string) ($user['name'] ?? ''));
        $name = $name !== '' ? $name : 'Xodim '.$employeeNo;
        $validFrom = $this->parseDeviceTime($user['beginTime'] ?? null) ?? now()->startOfDay();
        $validTo = $this->parseDeviceTime($user['endTime'] ?? null) ?? now()->addYears(10)->endOfDay();
        $gender = $this->mapDeviceGender($user['gender'] ?? null);

        if ($existing === null) {
            $employee = Employee::query()->create([
                'name' => $name,
                'card' => $employeeNo,
                'gender' => $gender,
                'department_id' => $department->id,
                'schedule_id' => $schedule->id,
                'valid_from_dt' => $validFrom,
                'valid_to_dt' => $validTo,
            ]);
            $outcome = 'created';
        } else {
            $employee = $existing;
            $fill = [
                'name' => $name,
                'valid_from_dt' => $validFrom,
                'valid_to_dt' => $validTo,
            ];

            if ($gender !== null) {
                $fill['gender'] = $gender;
            }

            $employee->fill($fill);
            $wasChanged = $employee->isDirty();
            $employee->save();
            $outcome = $wasChanged ? 'updated' : null;
        }

        $alreadyLinked = $device->employees()
            ->where('employees.id', $employee->id)
            ->exists();

        $device->employees()->syncWithoutDetaching([
            $employee->id => [
                'synced_at' => now(),
                'sync_error' => null,
            ],
        ]);

        $faceSaved = $this->syncPulledFace(
            $device,
            $employee,
            $employeeNo,
            $user['picURL'] ?? null,
        );

        if ($outcome === null) {
            $outcome = $alreadyLinked ? 'skipped' : 'linked';
        }

        // Faqat yuz yangilandi — "skipped" o‘rniga updated
        if ($faceSaved && $outcome === 'skipped') {
            $outcome = 'updated';
        }

        return [
            'outcome' => $outcome,
            'face_saved' => $faceSaved,
        ];
    }

    private function syncPulledFace(
        Device $device,
        Employee $employee,
        string $employeeNo,
        ?string $picUrl,
    ): bool {
        try {
            $base64 = $this->userService->downloadFaceBase64($device, $employeeNo, $picUrl);
        } catch (\Throwable $exception) {
            Log::warning('Hikvision: yuz tortib olishda xatolik', [
                'device_id' => $device->id,
                'employee_no' => $employeeNo,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }

        if ($base64 === null || $base64 === '') {
            return false;
        }

        $existingFace = $employee->face;

        if ($existingFace instanceof Face && hash_equals((string) $existingFace->face, $base64)) {
            return false;
        }

        Face::query()->updateOrCreate(
            ['employee_id' => $employee->id],
            ['face' => $base64],
        );

        return true;
    }

    private function resolveImportDepartment(): Department
    {
        $name = (string) config('hikvision.employees_pull_department', 'Qurilmadan');

        return Department::query()->firstOrCreate(['name' => $name]);
    }

    private function resolveImportSchedule(): Schedule
    {
        $name = (string) config('hikvision.employees_pull_schedule', 'Standart');

        return Schedule::query()->firstOrCreate(
            ['name' => $name],
            [
                'enter_time' => '09:00',
                'exit_time' => '18:00',
            ],
        );
    }

    private function parseDeviceTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function mapDeviceGender(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            '1', 'male', 'man', 'erkak' => Employee::GENDER_MALE,
            '2', 'female', 'woman', 'ayol' => Employee::GENDER_FEMALE,
            default => null,
        };
    }
}
