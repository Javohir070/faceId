<?php

namespace App\Services;

use App\Models\AccessControllerEvent;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Report;
use Carbon\Carbon;

class ReportService
{
    public function createFromAccessEvent(AccessControllerEvent $event): ?Report
    {
        if (! $this->shouldCreateReport($event)) {
            return null;
        }

        $employee = $this->resolveEmployee($event);
        $device = $this->resolveDevice($event);
        $type = $this->resolveType($event, $device);
        $occurredAt = $event->event_time ?? now();
        $employeeName = $employee?->name ?? $event->full_name ?? 'Noma’lum';

        // Event — tizim darajasidagi fakt: qo‘lda tortishni bo‘limga cheklangan foydalanuvchi boshlasa ham
        // xodim va dublikat bo‘lim cheklovisiz qidiriladi, aks holda report employee_id siz saqlanadi.
        $existingQuery = Report::query()->withoutGlobalScopes()->where('occurred_at', $occurredAt);

        if ($device !== null) {
            $existingQuery->where('device_id', $device->id);
        } else {
            $existingQuery->whereNull('device_id');
        }

        if ($employee !== null) {
            $existingQuery->where('employee_id', $employee->id);
        } else {
            $existingQuery->where('employee_name', $employeeName);
        }

        $existing = $existingQuery->first();

        if ($existing !== null) {
            return $existing;
        }

        return Report::create([
            'employee_id' => $employee?->id,
            'department_id' => $employee?->department_id,
            'device_id' => $device?->id,
            'employee_name' => $employeeName,
            'type' => $type,
            'occurred_at' => $occurredAt,
        ]);
    }

    private function shouldCreateReport(AccessControllerEvent $event): bool
    {
        if ($event->employee_no || $event->card_no || $event->full_name) {
            return true;
        }

        return $event->major_event_type === 5 && $event->sub_event_type === 75;
    }

    private function resolveEmployee(AccessControllerEvent $event): ?Employee
    {
        $employee = $event->employee()->withoutGlobalScopes()->first();

        if ($employee !== null) {
            return $employee->loadMissing('department');
        }

        if ($event->card_no) {
            $employee = Employee::query()->withoutGlobalScopes()->where('card', $event->card_no)->first();

            if ($employee !== null) {
                return $employee->loadMissing('department');
            }
        }

        return null;
    }

    private function resolveDevice(AccessControllerEvent $event): ?Device
    {
        return $event->device;
    }

    private function resolveType(AccessControllerEvent $event, ?Device $device): int
    {
        $status = mb_strtolower((string) $event->attendance_status);

        if (in_array($status, ['checkin', 'check_in', 'entry', 'in'], true)) {
            return Report::TYPE_ENTRY;
        }

        if (in_array($status, ['checkout', 'check_out', 'exit', 'out'], true)) {
            return Report::TYPE_EXIT;
        }

        if ($device !== null) {
            return $device->isExit() ? Report::TYPE_EXIT : Report::TYPE_ENTRY;
        }

        return Report::TYPE_ENTRY;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createFromWebhookPayload(array $payload, ?string $picturePath = null): ?Report
    {
        $employeeNo = data_get($payload, 'AccessControllerEvent.employeeNoString')
            ?? data_get($payload, 'AccessControllerEvent.employeeNo');
        $eventTime = data_get($payload, 'dateTime');
        $deviceIp = data_get($payload, 'ipAddress');
        $parsedTime = $eventTime ? Carbon::parse($eventTime)->format('Y-m-d H:i:s') : null;

        if ($employeeNo && $deviceIp && $parsedTime) {
            $exists = AccessControllerEvent::query()
                ->where('device_ip', $deviceIp)
                ->where('employee_no', (string) $employeeNo)
                ->where('event_time', $parsedTime)
                ->exists();

            if ($exists) {
                return null;
            }
        }

        $event = AccessControllerEvent::create([
            'employee_no' => $employeeNo,
            'card_no' => data_get($payload, 'AccessControllerEvent.cardNo'),
            'full_name' => data_get($payload, 'AccessControllerEvent.name'),
            'major_event_type' => data_get($payload, 'AccessControllerEvent.majorEventType')
                ?? data_get($payload, 'AccessControllerEvent.major'),
            'sub_event_type' => data_get($payload, 'AccessControllerEvent.subEventType')
                ?? data_get($payload, 'AccessControllerEvent.minor'),
            'verify_mode' => data_get($payload, 'AccessControllerEvent.currentVerifyMode'),
            'user_type' => data_get($payload, 'AccessControllerEvent.userType'),
            'attendance_status' => data_get($payload, 'AccessControllerEvent.attendanceStatus'),
            'event_time' => $parsedTime,
            'device_ip' => $deviceIp,
            'picture' => $picturePath,
            'payload' => $payload,
        ]);

        return $this->createFromAccessEvent($event);
    }
}
