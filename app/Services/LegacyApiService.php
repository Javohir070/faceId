<?php

namespace App\Services;

use App\Enums\NoteType;
use App\Interfaces\EmployeeRepositoryInterface;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Face;
use App\Models\Note;
use App\Models\Report;
use App\Models\Schedule;
use App\Services\Hikvision\EmployeeSyncService;
use App\Services\Reports\AttendanceCalculator;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Eski Biocontrol tizimining api.php’si bilan bir xil javob beradigan API (docs/legacy-api.md).
 *
 * Tashqi tizimlar hech narsani o‘zgartirmasligi uchun maydon nomlari, raqamlarning satr bo‘lib
 * qaytishi va xato matnlari ham eskisidek, ya’ni inglizcha. Foydalanuvchi yo‘q, shuning uchun
 * bo‘lim/qurilma cheklovlari (global scope) qo‘llanmaydi: eski API ham hammasini ko‘rardi.
 */
class LegacyApiService
{
    /** Eski API kechikishni shu chegaralar bilan ajratardi (ish jadvalidan qat’i nazar). */
    private const LATE_FROM = '09:16:00';

    private const VERY_LATE_AFTER = '09:30:00';

    /** Eski tizimdagi templates jadvali: 1 = Authorized, 2 = Blocked. */
    private const TEMPLATE_AUTHORIZED = '1';

    private const TEMPLATE_BLOCKED = '2';

    /** employees.card ustuni varchar(20). */
    private const CARD_MAX_LENGTH = 20;

    /** @var array<string, NoteType> */
    private const NOTE_TYPES = [
        'came_late' => NoteType::CameLate,
        'left_early' => NoteType::LeftEarly,
        'absent' => NoteType::Absent,
    ];

    public function __construct(
        private readonly EmployeeRepositoryInterface $employeeRepository,
        private readonly EmployeeService $employeeService,
        private readonly NoteService $noteService,
        private readonly EmployeeSyncService $employeeSyncService,
        private readonly AttendanceCalculator $calculator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(mixed $json): array
    {
        if (! is_array($json) || $json === []) {
            return ['status' => false];
        }

        $action = $json['action'] ?? null;

        try {
            return match ($action) {
                'add_employee' => $this->addEmployee($json),
                'delete_employee' => $this->deleteEmployees($json),
                'attendance' => $this->attendance($json),
                'add_note' => $this->addNote($json),
                'get_reports' => $this->reports($json),
                'get_work_time' => $this->workTime($json),
                default => ['status' => false],
            };
        } catch (\Throwable $exception) {
            Log::error('Legacy API: so‘rovni bajarishda xatolik', [
                'action' => is_scalar($action) ? $action : null,
                'message' => $exception->getMessage(),
            ]);

            return $this->failed(['Internal error']);
        }
    }

    /**
     * Karta (JSHSHIR) bo‘yicha xodimni yaratadi yoki yangilaydi va eskidek darhol barcha terminallarga yozadi.
     *
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function addEmployee(array $json): array
    {
        $name = trim($this->text($json, 'name'));
        $card = trim($this->text($json, 'card'));
        $template = $this->text($json, 'template');
        $face = $this->text($json, 'face');
        $start = $this->text($json, 'start');
        $end = $this->text($json, 'end');

        $alerts = [];

        if (! $name) {
            $alerts[] = 'Enter name';
        }
        if (! $template) {
            $alerts[] = 'Select template';
        }
        if (! $start) {
            $alerts[] = 'Enter start datetime';
        }
        if (! $end) {
            $alerts[] = 'Enter end datetime';
        }

        if ($template && ! in_array($template, [self::TEMPLATE_AUTHORIZED, self::TEMPLATE_BLOCKED], true)) {
            $alerts[] = 'Invalid template';
        }
        if (mb_strlen($card) > self::CARD_MAX_LENGTH) {
            $alerts[] = 'Invalid card number';
        }

        $validFrom = $start ? $this->parseDateTime($start) : null;
        $validTo = $end ? $this->parseDateTime($end) : null;

        if ($start && $validFrom === null) {
            $alerts[] = 'Invalid start datetime';
        }
        if ($end && $validTo === null) {
            $alerts[] = 'Invalid end datetime';
        }

        $faceBase64 = null;
        if ($face) {
            $faceBase64 = $this->normalizeFace($face);

            if ($faceBase64 === null) {
                $alerts[] = 'Invalid face image';
            }
        }

        if ($alerts !== []) {
            return $this->failed($alerts);
        }

        if (! $card) {
            $card = $this->generateCard();
        }

        $employee = Employee::query()->withoutGlobalScopes()->where('card', $card)->first();
        $defaults = null;

        if ($employee === null) {
            $defaults = $this->defaultsForNewEmployee($alerts);

            if ($defaults === null) {
                return $this->failed($alerts);
            }
        }

        // Blocked: terminaldagi foydalanuvchi qoladi, lekin amal qilish muddati hozir tugaydi — eskidek o‘tolmaydi.
        $blocked = $template === self::TEMPLATE_BLOCKED;
        if ($blocked) {
            $validTo = now();
            if ($validFrom->greaterThan($validTo)) {
                $validFrom = $validTo->copy();
            }
        }

        $employee = DB::transaction(function () use ($employee, $defaults, $name, $card, $validFrom, $validTo, $blocked, $faceBase64): Employee {
            $data = [
                'name' => $name,
                'valid_from_dt' => $validFrom,
                'valid_to_dt' => $validTo,
            ];

            $employee = $employee === null
                ? $this->employeeRepository->create([...$data, 'card' => $card, ...$defaults])
                : $this->employeeRepository->update($employee, $data);

            if ($blocked) {
                // Bloklangan xodim uchun «muddati tugadi» Telegram xabari yuborilmasin.
                Employee::query()->withoutGlobalScopes()->whereKey($employee->id)->update([
                    'expiry_warned_at' => now(),
                    'expiry_notified_at' => now(),
                ]);
            }

            if ($faceBase64 !== null) {
                Face::query()->updateOrCreate(
                    ['employee_id' => $employee->id],
                    ['face' => $faceBase64],
                );
            }

            return $employee->unsetRelation('face');
        });

        foreach (Device::query()->withoutGlobalScopes()->orderBy('id')->get() as $device) {
            $result = $this->employeeSyncService->transferOne($device, $employee);

            if ($result['status'] !== 'success') {
                $alerts[] = [
                    'status' => false,
                    'message' => sprintf('IP: %s; ID: %d; Error: %s', $device->ip_address, $employee->id, $result['message']),
                ];
            }
        }

        if ($alerts !== []) {
            return $this->failed($alerts);
        }

        return ['status' => true, 'alerts' => ['Saved successfully']];
    }

    /**
     * Har bir xodim avval terminallardan olinadi; hammasidan olingandagina bazadan o‘chiriladi.
     *
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function deleteEmployees(array $json): array
    {
        $cards = $this->strings($json['cards'] ?? []);
        $alerts = [];

        if ($cards !== []) {
            $employees = Employee::query()->withoutGlobalScopes()
                ->whereIn('card', $cards)
                ->orderBy('id')
                ->get();

            foreach ($employees as $employee) {
                $deviceIds = $employee->devices()->withoutGlobalScopes()->pluck('devices.id')->all();
                $errors = $deviceIds === []
                    ? []
                    : ($this->employeeSyncService->removeFromDevices($employee, $deviceIds)['errors'] ?? []);

                foreach ($errors as $error) {
                    $alerts[] = $error['ip'].' '.$error['message'];
                }

                if ($errors === []) {
                    $this->employeeService->delete($employee);
                }
            }
        }

        if ($alerts !== []) {
            return $this->failed($alerts);
        }

        return ['status' => true, 'alerts' => ['Deleted successfully']];
    }

    /**
     * Bugungi birinchi kirish va 09:15 / 09:30 bo‘yicha belgi, bugungi izoh bilan.
     *
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function attendance(array $json): array
    {
        $date = Carbon::now($this->eventTimezone())->toDateString();
        $cards = $this->strings($json['cards'] ?? []);

        $employees = Employee::query()->withoutGlobalScopes()
            ->when($cards !== [], fn ($query) => $query->whereIn('card', $cards))
            ->orderBy('id')
            ->get(['id', 'name', 'card']);

        $firstEntries = Report::query()->withoutGlobalScopes()
            ->where('type', Report::TYPE_ENTRY)
            ->whereNotNull('employee_id')
            ->whereBetween('occurred_at', [$date.' 00:00:00', $date.' 23:59:59'])
            ->groupBy('employee_id')
            ->selectRaw('employee_id, MIN(occurred_at) AS first_entry')
            ->pluck('first_entry', 'employee_id');

        $notes = Note::query()->withoutGlobalScopes()
            ->whereDate('dt', $date)
            ->orderBy('id')
            ->get(['employee_id', 'type', 'note'])
            ->groupBy('employee_id');

        $rows = [];

        foreach ($employees as $employee) {
            $firstEntry = $firstEntries[$employee->id] ?? null;
            $dt = $firstEntry !== null ? Carbon::parse($firstEntry)->format('Y-m-d H:i:s') : null;
            $employeeNotes = $notes->get($employee->id, collect());

            // Eskidek: kelgan bo‘lsa kunning istalgan izohi, kelmagan bo‘lsa faqat «kelmadi» izohi.
            $note = $dt !== null
                ? $employeeNotes->first()
                : $employeeNotes->first(fn (Note $note): bool => $note->type === NoteType::Absent);

            $time = $dt !== null ? substr($dt, 11) : null;

            $rows[] = [
                'name' => $employee->name,
                'card' => $employee->card,
                'note_type' => $note !== null ? $this->noteTypeKey($note->type) : null,
                'note' => $note?->note,
                'dt' => $dt,
                'before_9_15' => $time !== null && $time < self::LATE_FROM,
                'between_9_15_9_30' => $time !== null && $time >= self::LATE_FROM && $time <= self::VERY_LATE_AFTER,
                'after_9_30' => $time !== null && $time > self::VERY_LATE_AFTER,
                'absent' => $time === null,
            ];
        }

        return ['status' => true, 'attendance' => $rows];
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function addNote(array $json): array
    {
        $card = trim($this->text($json, 'card'));
        $type = $this->text($json, 'type');
        $dt = $this->text($json, 'dt');
        $text = $this->text($json, 'note');

        $alerts = [];

        $employee = $card !== ''
            ? Employee::query()->withoutGlobalScopes()->where('card', $card)->first()
            : null;

        if ($employee === null) {
            $alerts[] = 'Invalid card number';
        }

        $noteType = self::NOTE_TYPES[$type] ?? null;
        if ($noteType === null) {
            $alerts[] = 'Invalid note type';
        }

        if (! $text) {
            $alerts[] = 'Invalid note text';
        }

        $date = $dt ? $this->parseDateTime($dt)?->toDateString() : null;
        if ($date === null) {
            $alerts[] = 'Invalid date';
        }

        if ($alerts !== []) {
            return $this->failed($alerts);
        }

        // Bir kun va turga bitta izoh (notes jadvalidagi unique): qayta yuborilsa matni yangilanadi.
        $existing = Note::query()->withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('type', $noteType->value)
            ->whereDate('dt', $date)
            ->first();

        if ($existing !== null) {
            $this->noteService->update($existing, ['note' => $text]);
        } else {
            $this->noteService->create([
                'employee_id' => $employee->id,
                'type' => $noteType,
                'dt' => $date,
                'note' => $text,
            ]);
        }

        return ['status' => true];
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function reports(array $json): array
    {
        $card = $this->text($json, 'card');
        $ip = $this->text($json, 'ip');
        $type = $this->text($json, 'type');

        [$from, $to, $alerts] = $this->period($json);

        if ($alerts !== []) {
            return $this->failed($alerts);
        }

        $rows = DB::table('reports as r')
            ->leftJoin('devices as d', 'd.id', '=', 'r.device_id')
            ->leftJoin('employees as e', 'e.id', '=', 'r.employee_id')
            ->where('r.occurred_at', '>=', $from->format('Y-m-d H:i:s'))
            ->where('r.occurred_at', '<=', $to->format('Y-m-d H:i:s'))
            ->when($card, fn ($query) => $query->where('e.card', $card))
            ->when($ip, fn ($query) => $query->where('d.ip_address', $ip))
            ->when($type, fn ($query) => $query->where('r.type', $type))
            ->orderBy('r.occurred_at')
            ->orderBy('r.id')
            ->select(['r.id', 'e.card', 'd.ip_address as ip', 'r.type', 'r.occurred_at as dt'])
            ->cursor();

        $reports = [];

        // Eski API mysqli’dan kelgani uchun raqamlarni ham satr qilib qaytarardi.
        foreach ($rows as $row) {
            $reports[] = [
                'id' => (string) $row->id,
                'card' => $row->card,
                'ip' => $row->ip,
                'type' => (string) $row->type,
                'dt' => substr((string) $row->dt, 0, 19),
            ];
        }

        return ['status' => true, 'reports' => $reports];
    }

    /**
     * Har kuni uchun oxirgi chiqish − birinchi kirish (tungi smenada 12 soat siljitilgan ish kuni bo‘yicha).
     *
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function workTime(array $json): array
    {
        $card = $this->text($json, 'card');

        [$from, $to, $alerts] = $this->period($json);

        if ($alerts !== []) {
            return $this->failed($alerts);
        }

        $dates = collect(CarbonPeriod::create($from->toDateString(), $to->toDateString()))
            ->map(fn (CarbonInterface $day): string => $day->toDateString())
            ->all();

        // Tungi smena chegaradagi kunlarga to‘g‘ri tushishi uchun 12 soat oldin va keyin ham olinadi.
        $rows = DB::table('reports as r')
            ->join('employees as e', 'e.id', '=', 'r.employee_id')
            ->leftJoin('schedules as s', 's.id', '=', 'e.schedule_id')
            ->whereBetween('r.occurred_at', [
                Carbon::parse($from->toDateString())->subHours(12)->format('Y-m-d H:i:s'),
                Carbon::parse($to->toDateString())->endOfDay()->addHours(12)->format('Y-m-d H:i:s'),
            ])
            ->when($card, fn ($query) => $query->where('e.card', $card))
            ->orderBy('r.employee_id')
            ->orderBy('r.occurred_at')
            ->select(['r.employee_id', 'e.card', 'r.type', 'r.occurred_at', 's.enter_time', 's.exit_time'])
            ->cursor();

        /** @var array<int, array{card: string, shifts: array<string, array{first: Carbon, entry: ?Carbon, exit: ?Carbon}>}> $employees */
        $employees = [];

        foreach ($rows as $row) {
            $at = Carbon::parse($row->occurred_at);
            $night = $row->enter_time !== null && $row->exit_time !== null
                && $this->calculator->isNightShift((string) $row->enter_time, (string) $row->exit_time);
            $workDate = $this->calculator->workDate($at, $night);

            $employees[$row->employee_id]['card'] = (string) $row->card;
            $shift = $employees[$row->employee_id]['shifts'][$workDate] ?? ['first' => $at, 'entry' => null, 'exit' => null];

            if ((int) $row->type === Report::TYPE_ENTRY && $shift['entry'] === null) {
                $shift['entry'] = $at;
            }
            if ((int) $row->type === Report::TYPE_EXIT) {
                $shift['exit'] = $at;
            }

            $employees[$row->employee_id]['shifts'][$workDate] = $shift;
        }

        $workTime = [];

        foreach ($employees as $employee) {
            // Eskidek kun kaliti — smenadagi eng birinchi hodisaning sanasi.
            $byDate = [];
            foreach ($employee['shifts'] as $shift) {
                $byDate[$shift['first']->toDateString()] = $this->duration($shift['entry'], $shift['exit']);
            }

            if (array_intersect(array_keys($byDate), $dates) === []) {
                continue;
            }

            $days = [];
            $total = 0;

            foreach ($dates as $date) {
                $duration = $byDate[$date] ?? null;
                $days[$date] = $duration['text'] ?? 0;

                if ($duration !== null && $duration['seconds'] > 0) {
                    $total += $duration['seconds'];
                }
            }

            $workTime[] = [
                'card' => $employee['card'],
                'days' => $days,
                'total' => $this->clock($total),
            ];
        }

        return ['status' => true, 'work_time' => $workTime];
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{0: ?Carbon, 1: ?Carbon, 2: list<string>}
     */
    private function period(array $json): array
    {
        $start = $this->text($json, 'start');
        $end = $this->text($json, 'end');
        $alerts = [];

        if (! $start) {
            $alerts[] = 'Enter start datetime';
        }
        if (! $end) {
            $alerts[] = 'Enter end datetime';
        }

        $from = $start ? $this->parseDateTime($start) : null;
        $to = $end ? $this->parseDateTime($end) : null;

        if ($start && $from === null) {
            $alerts[] = 'Invalid start datetime';
        }
        if ($end && $to === null) {
            $alerts[] = 'Invalid end datetime';
        }

        return [$from, $to, $alerts];
    }

    /**
     * @param  list<string>  $alerts
     * @return array{department_id: int, schedule_id: int}|null
     */
    private function defaultsForNewEmployee(array &$alerts): ?array
    {
        $departmentId = (int) config('legacy_api.department_id');
        $scheduleId = (int) config('legacy_api.schedule_id');

        if ($departmentId <= 0 || ! Department::query()->withoutGlobalScopes()->whereKey($departmentId)->exists()) {
            $alerts[] = 'LEGACY_API_DEPARTMENT_ID is not configured';
        }
        if ($scheduleId <= 0 || ! Schedule::query()->withoutGlobalScopes()->whereKey($scheduleId)->exists()) {
            $alerts[] = 'LEGACY_API_SCHEDULE_ID is not configured';
        }

        if ($alerts !== []) {
            Log::error('Legacy API: yangi xodim uchun bo‘lim yoki ish jadvali sozlanmagan');

            return null;
        }

        return ['department_id' => $departmentId, 'schedule_id' => $scheduleId];
    }

    /**
     * Eski API karta berilmasa 8 xonali tasodifiy raqam berardi.
     */
    private function generateCard(): string
    {
        do {
            $card = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        } while (Employee::query()->withoutGlobalScopes()->where('card', $card)->exists());

        return $card;
    }

    /**
     * Base64 JPEG/PNG (data: prefiksi bilan ham) — rasm bo‘lmasa null.
     */
    private function normalizeFace(string $face): ?string
    {
        $base64 = preg_replace('/^data:image\/[a-z0-9.+-]+;base64,/i', '', trim($face));
        $base64 = preg_replace('/\s+/', '', (string) $base64);
        $binary = base64_decode((string) $base64, true);

        if ($binary === false || $binary === '' || @getimagesizefromstring($binary) === false) {
            return null;
        }

        return $base64;
    }

    /**
     * @return array{seconds: int, text: string}|null
     */
    private function duration(?Carbon $entry, ?Carbon $exit): ?array
    {
        if ($entry === null || $exit === null) {
            return null;
        }

        $seconds = $exit->getTimestamp() - $entry->getTimestamp();

        return [
            'seconds' => $seconds,
            'text' => ($seconds < 0 ? '-' : '').$this->clock(abs($seconds)),
        ];
    }

    private function clock(int $seconds): string
    {
        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds, 60) % 60, $seconds % 60);
    }

    private function noteTypeKey(NoteType $type): string
    {
        return match ($type) {
            NoteType::CameLate => 'came_late',
            NoteType::LeftEarly => 'left_early',
            NoteType::Absent => 'absent',
        };
    }

    private function parseDateTime(string $value): ?Carbon
    {
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function eventTimezone(): string
    {
        return (string) config('hikvision.event_timezone', 'Asia/Tashkent');
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function text(array $json, string $key): string
    {
        $value = $json[$key] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn ($value): string => is_scalar($value) ? trim((string) $value) : '', $values),
            fn (string $value): bool => $value !== '',
        ));
    }

    /**
     * @param  list<string|array<string, mixed>>  $alerts
     * @return array{status: false, alerts: list<string|array<string, mixed>>}
     */
    private function failed(array $alerts): array
    {
        return ['status' => false, 'alerts' => $alerts];
    }
}
