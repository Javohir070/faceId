<?php

namespace App\Services;

use App\Models\AccessControllerEvent;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Report;
use App\Services\Reports\AttendanceCalculator;
use Carbon\Carbon;

class ReportService
{
    /** Shu oraliqda qayta tanilsa (terminal ikki marta o‘qisa) navbat almashmaydi. */
    private const REPEAT_WINDOW_SECONDS = 120;

    public function __construct(
        private readonly AttendanceCalculator $calculator,
    ) {}

    public function createFromAccessEvent(AccessControllerEvent $event): ?Report
    {
        if (! $this->shouldCreateReport($event)) {
            return null;
        }

        $employee = $this->resolveEmployee($event);
        $device = $this->resolveDevice($event);
        $occurredAt = $event->event_time ?? now();
        $type = $this->resolveType($event, $device, $employee, Carbon::parse($occurredAt));
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

    /**
     * Terminal ekranidagi davomat tugmasi (attendanceStatus) qurilma turidan ustun: bitta terminal
     * "Ishga keldim" va "Chiqdim" ni ham yozadi. Tugma bosilmasa ("undefined" yoki yo‘q) — qurilma turi;
     * "Kirish va chiqish" turidagi terminalda esa kun ichidagi navbat (alternatingType).
     */
    private function resolveType(AccessControllerEvent $event, ?Device $device, ?Employee $employee, Carbon $occurredAt): int
    {
        $status = mb_strtolower((string) $event->attendance_status);

        if (in_array($status, ['checkin', 'check_in', 'breakin', 'overtimein', 'entry', 'in'], true)) {
            return Report::TYPE_ENTRY;
        }

        if (in_array($status, ['checkout', 'check_out', 'breakout', 'overtimeout', 'exit', 'out'], true)) {
            return Report::TYPE_EXIT;
        }

        if ($device?->isEntryAndExit()) {
            return $this->alternatingType($employee, $occurredAt);
        }

        if ($device !== null) {
            return $device->isExit() ? Report::TYPE_EXIT : Report::TYPE_ENTRY;
        }

        return Report::TYPE_ENTRY;
    }

    /**
     * Xodimning shu ish kunidagi oldingi o‘tishiga qarab (barcha qurilmalar bo‘yicha): kirish bo‘lsa — chiqish,
     * aks holda — kirish. Har ish kuni kirishdan boshlanadi; tungi smenada ish kuni 12 soat siljitiladi
     * (Davomat bilan bir xil). Qayta tanish (REPEAT_WINDOW_SECONDS ichida) oldingi turini takrorlaydi.
     */
    private function alternatingType(?Employee $employee, Carbon $occurredAt): int
    {
        if ($employee === null) {
            return Report::TYPE_ENTRY;
        }

        $previous = Report::query()
            ->withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('occurred_at', '<', $occurredAt->format('Y-m-d H:i:s'))
            ->where('occurred_at', '>=', $occurredAt->copy()->subDay()->format('Y-m-d H:i:s'))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->first(['id', 'type', 'occurred_at']);

        if ($previous === null) {
            return Report::TYPE_ENTRY;
        }

        if ($previous->occurred_at->diffInSeconds($occurredAt, true) <= self::REPEAT_WINDOW_SECONDS) {
            return $previous->type;
        }

        $schedule = $employee->schedule;
        $enter = $schedule?->enter_time?->format('H:i:s');
        $exit = $schedule?->exit_time?->format('H:i:s');
        $night = $enter !== null && $exit !== null && $this->calculator->isNightShift($enter, $exit);

        if ($this->calculator->workDate($previous->occurred_at, $night) !== $this->calculator->workDate($occurredAt, $night)) {
            return Report::TYPE_ENTRY;
        }

        return $previous->type === Report::TYPE_ENTRY ? Report::TYPE_EXIT : Report::TYPE_ENTRY;
    }

    /**
     * Qurilma vaqtini hikvision.event_timezone (Asia/Tashkent) ga o‘giradi. Terminal boshqa zonada sozlangan
     * bo‘lsa (masalan zavod sozlamasi UTC+8) ham hodisa haqiqiy Toshkent vaqtida saqlanadi; +05:00 vaqt
     * o‘zgarmaydi. Zonasiz vaqt shu zonada deb olinadi.
     */
    public static function localEventTime(string $eventTime): string
    {
        $timezone = config('hikvision.event_timezone') ?: config('app.timezone');

        return Carbon::parse($eventTime, $timezone)->setTimezone($timezone)->format('Y-m-d H:i:s');
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
        $parsedTime = $eventTime ? self::localEventTime($eventTime) : null;

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
