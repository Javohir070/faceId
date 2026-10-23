<?php

namespace App\Services\Attendance;

use App\Models\Employee;
use App\Models\Note;
use App\Models\Report;
use App\Services\Reports\AttendanceCalculator;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Davomat jadvali: xodim × ish kuni kataklari va statistika. Faqat bazadan o‘qiydi.
 *
 * Qoidalar spec'da: docs/superpowers/specs/2026-10-23-attendance-grid-design.md
 */
class AttendanceGridService
{
    public const LUNCH_SECONDS = 3600;

    private const COUNTED = ['on_time', 'late', 'excused', 'absent'];

    public function __construct(
        private readonly AttendanceCalculator $calculator,
    ) {}

    /**
     * @param  Collection<int, Employee>  $employees
     * @return array{rows: list<array<string, mixed>>, stats: array<string, mixed>}
     */
    public function build(Collection $employees, AttendancePeriod $period, CarbonInterface $now): array
    {
        $employees = EloquentCollection::make($employees->all())->loadMissing(['schedule', 'department:id,name']);
        $dates = $period->workDates();
        $today = $now->toDateString();

        $punches = $this->punchesByEmployeeDate($employees, $period);
        $notes = $this->noteKeys($employees->modelKeys(), $period);

        $counts = array_fill_keys(self::COUNTED, 0);
        $early = 0;
        $arrivals = [];
        $workedTotal = 0;
        $planTotal = 0;
        $rows = [];

        foreach ($employees as $employee) {
            $schedule = $this->scheduleInfo($employee);
            $validFrom = $employee->valid_from_dt?->toDateString();
            $validTo = $employee->valid_to_dt?->toDateString();
            $cells = [];
            $hasValidDay = false;
            $rowWorked = 0;

            foreach ($dates as $date) {
                if (($validFrom !== null && $date < $validFrom) || ($validTo !== null && $date > $validTo)) {
                    $cells[] = $this->emptyCell($date, 'inactive');

                    continue;
                }

                $hasValidDay = true;
                $cell = $this->cell(
                    $date,
                    $punches[$employee->id][$date] ?? [],
                    isset($notes[$employee->id.'|'.$date]),
                    $schedule,
                    $today,
                );
                $cells[] = $cell['public'];

                if (isset($counts[$cell['public']['status']])) {
                    $counts[$cell['public']['status']]++;
                }
                if ($cell['public']['early']) {
                    $early++;
                }
                if ($cell['arrival'] !== null) {
                    $arrivals[] = $cell['arrival'];
                }
                if ($cell['completed']) {
                    $planTotal += $schedule['plan'];
                }
                $rowWorked += $cell['public']['worked_seconds'];
            }

            if (! $hasValidDay) {
                continue;
            }

            $workedTotal += $rowWorked;
            $rows[] = [
                'id' => $employee->id,
                'name' => $employee->name,
                'position' => $employee->position,
                'department' => [
                    'id' => (int) $employee->department_id,
                    'name' => (string) $employee->department?->name,
                ],
                'worked_seconds' => $rowWorked,
                'worked' => self::formatDuration($rowWorked),
                'cells' => $cells,
            ];
        }

        return [
            'rows' => $rows,
            'stats' => $this->stats($counts, $early, $arrivals, $workedTotal, $planTotal, $dates, $today),
        ];
    }

    /**
     * Bitta xodim-kun tafsiloti (kun oynasi uchun). Holat va vaqtlar build() dagi katak bilan bir xil.
     *
     * @return array<string, mixed>
     */
    public function day(Employee $employee, string $date, CarbonInterface $now): array
    {
        $employee->loadMissing(['schedule', 'department:id,name']);
        $period = AttendancePeriod::make('week', Carbon::parse($date));
        $row = $this->build(EloquentCollection::make([$employee]), $period, $now)['rows'][0] ?? null;
        $cell = collect($row['cells'] ?? [])->firstWhere('date', $date) ?? $this->emptyCell($date, 'inactive');
        $schedule = $this->scheduleInfo($employee);

        $punches = Report::query()
            ->with('device.category:id,name')
            ->where('employee_id', $employee->id)
            ->where('occurred_at', '>=', Carbon::parse($date)->format('Y-m-d H:i:s'))
            ->where('occurred_at', '<', Carbon::parse($date)->addDay()->addHours(12)->format('Y-m-d H:i:s'))
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (Report $report): bool => $this->calculator->workDate($report->occurred_at, $schedule['night']) === $date)
            ->map(fn (Report $report): array => [
                'time' => $report->occurred_at->format('H:i'),
                'type' => $report->type === Report::TYPE_ENTRY ? 'entry' : 'exit',
                'label' => $report->type === Report::TYPE_ENTRY ? 'Kirdi' : 'Chiqdi',
                'place' => $report->device === null
                    ? 'Noma’lum qurilma'
                    : implode(' · ', array_filter([$report->device->category?->name, $report->device->name])),
            ])
            ->values()
            ->all();

        $notes = Note::query()
            ->where('employee_id', $employee->id)
            ->whereDate('dt', $date)
            ->orderBy('id')
            ->get()
            ->map(fn (Note $note): array => [
                'id' => $note->id,
                'type' => $note->type->value,
                'type_label' => $note->type->label(),
                'note' => (string) $note->note,
            ])
            ->all();

        return [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'position' => $employee->position,
            ],
            'date' => $date,
            'status' => $cell['status'],
            'in' => $cell['in'],
            'out' => $cell['out'],
            'inside' => $cell['inside'],
            'worked' => self::formatDuration($cell['worked_seconds']),
            'schedule' => substr($schedule['enter'], 0, 5).'–'.substr($schedule['exit'], 0, 5),
            'punches' => $punches,
            'notes' => $notes,
        ];
    }

    public static function formatDuration(int $seconds): string
    {
        $seconds = max(0, $seconds);

        return sprintf('%d s %02d d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }

    /**
     * @param  list<array{type: int, at: Carbon}>  $punches  vaqt bo‘yicha tartiblangan
     * @param  array{enter: string, exit: string, night: bool, plan: int}  $schedule
     * @return array{public: array<string, mixed>, completed: bool, arrival: ?int}
     */
    private function cell(string $date, array $punches, bool $hasNote, array $schedule, string $today): array
    {
        if ($date > $today) {
            return ['public' => $this->emptyCell($date, 'future'), 'completed' => false, 'arrival' => null];
        }

        $firstEntry = null;
        $lastExit = null;
        foreach ($punches as $punch) {
            if ($punch['type'] === Report::TYPE_ENTRY) {
                $firstEntry ??= $punch['at'];
            } elseif ($punch['type'] === Report::TYPE_EXIT) {
                $lastExit = $punch['at'];
            }
        }
        $lastPunch = $punches === [] ? null : $punches[array_key_last($punches)];
        $isToday = $date === $today;
        $lastIsEntry = $lastPunch !== null && $lastPunch['type'] === Report::TYPE_ENTRY;
        $completed = ! $isToday || ($lastPunch !== null && ! $lastIsEntry);

        $status = match (true) {
            $hasNote => 'excused',
            $firstEntry !== null => $this->calculator->lateSeconds($firstEntry, $schedule['enter'], $schedule['night']) > 0
                ? 'late'
                : 'on_time',
            ! $isToday => 'absent',
            default => 'pending',
        };

        $early = $completed
            && $lastExit !== null
            && $this->calculator->earlySeconds($lastExit, $schedule['exit'], $schedule['night']) > 0;

        $worked = $completed
            ? max(0, $this->calculator->workedSeconds($firstEntry, $lastExit) - self::LUNCH_SECONDS)
            : 0;

        $arrival = null;
        if ($firstEntry !== null) {
            $moment = $schedule['night'] ? $this->calculator->shiftMoment($firstEntry) : $firstEntry;
            $arrival = $moment->secondsSinceMidnight();
        }

        return [
            'public' => [
                'date' => $date,
                'status' => $status,
                'in' => $firstEntry?->format('H:i'),
                'out' => $lastExit?->format('H:i'),
                'inside' => $isToday && $lastIsEntry,
                'early' => $early,
                'worked_seconds' => $worked,
            ],
            'completed' => $completed,
            'arrival' => $arrival,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyCell(string $date, string $status): array
    {
        return [
            'date' => $date,
            'status' => $status,
            'in' => null,
            'out' => null,
            'inside' => false,
            'early' => false,
            'worked_seconds' => 0,
        ];
    }

    /**
     * @param  array<string, int>  $counts
     * @param  list<int>  $arrivals
     * @param  list<string>  $dates
     * @return array<string, mixed>
     */
    private function stats(array $counts, int $early, array $arrivals, int $worked, int $plan, array $dates, string $today): array
    {
        $total = array_sum($counts);
        $percent = [];
        foreach (self::COUNTED as $key) {
            $percent[$key] = $total === 0 ? 0 : (int) round($counts[$key] * 100 / $total);
        }

        $avgArrival = null;
        if ($arrivals !== []) {
            $avg = (int) round(array_sum($arrivals) / count($arrivals));
            $avgArrival = sprintf('%02d:%02d', intdiv($avg, 3600), intdiv($avg % 3600, 60));
        }

        return [
            'work_days' => count($dates),
            'elapsed_days' => count(array_filter($dates, fn (string $date): bool => $date <= $today)),
            'avg_arrival' => $avgArrival,
            ...$counts,
            'early' => $early,
            'percent' => $percent,
            'worked_seconds' => $worked,
            'worked' => self::formatDuration($worked),
            'plan_seconds' => $plan,
            'plan' => self::formatDuration($plan),
        ];
    }

    /**
     * @return array{enter: string, exit: string, night: bool, plan: int}
     */
    private function scheduleInfo(Employee $employee): array
    {
        $enter = (string) $employee->schedule?->enter_time?->format('H:i:s');
        $exit = (string) $employee->schedule?->exit_time?->format('H:i:s');
        $night = $enter !== '' && $exit !== '' && $this->calculator->isNightShift($enter, $exit);

        $duration = 0;
        if ($enter !== '' && $exit !== '') {
            $duration = Carbon::parse($exit)->secondsSinceMidnight() - Carbon::parse($enter)->secondsSinceMidnight();
            if ($night) {
                $duration += 86400;
            }
        }

        return [
            'enter' => $enter ?: '00:00:00',
            'exit' => $exit ?: '23:59:59',
            'night' => $night,
            'plan' => max(0, $duration - self::LUNCH_SECONDS),
        ];
    }

    /**
     * Davr o‘tishlarini xodim va ish kuni (tungi smenada siljitilgan) bo‘yicha guruhlaydi.
     *
     * @param  EloquentCollection<int, Employee>  $employees
     * @return array<int, array<string, list<array{type: int, at: Carbon}>>>
     */
    private function punchesByEmployeeDate(EloquentCollection $employees, AttendancePeriod $period): array
    {
        if ($employees->isEmpty()) {
            return [];
        }

        $night = [];
        foreach ($employees as $employee) {
            $night[$employee->id] = $this->scheduleInfo($employee)['night'];
        }

        $dates = array_flip($period->workDates());
        $map = [];

        // Tungi smena chiqishi ertasi kuni ertalab bo‘ladi — oxirgi kundan keyin 12 soat ham olinadi.
        $reports = Report::query()
            ->whereIn('employee_id', $employees->modelKeys())
            ->where('occurred_at', '>=', $period->start->format('Y-m-d H:i:s'))
            ->where('occurred_at', '<', $period->end->addDay()->startOfDay()->addHours(12)->format('Y-m-d H:i:s'))
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->toBase()
            ->select(['employee_id', 'type', 'occurred_at'])
            ->cursor();

        foreach ($reports as $report) {
            $employeeId = (int) $report->employee_id;
            $at = Carbon::parse($report->occurred_at);
            $date = $this->calculator->workDate($at, $night[$employeeId] ?? false);

            if (isset($dates[$date])) {
                $map[$employeeId][$date][] = ['type' => (int) $report->type, 'at' => $at];
            }
        }

        return $map;
    }

    /**
     * @param  list<int>  $employeeIds
     * @return array<string, true>
     */
    private function noteKeys(array $employeeIds, AttendancePeriod $period): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $keys = [];
        $notes = Note::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('dt', '>=', $period->start->toDateString())
            ->whereDate('dt', '<=', $period->end->toDateString())
            ->get(['employee_id', 'dt']);

        foreach ($notes as $note) {
            $keys[$note->employee_id.'|'.$note->dt?->toDateString()] = true;
        }

        return $keys;
    }
}
