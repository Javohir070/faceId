<?php

namespace App\Services\Units;

use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Models\Note;
use App\Models\Report;
use App\Services\Groups\GroupTree;
use App\Services\Reports\AttendanceCalculator;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Tarkibiy bo‘linmalar: hozir kim binoda, kim yo‘q — guruh kartochkalari bo‘yicha. Faqat bazadan o‘qiydi.
 * Global scope'lar ishlaydi: foydalanuvchi faqat o‘z bo‘limini ko‘radi.
 *
 * Qoidalar spec'da: docs/superpowers/specs/2026-11-12-structural-units-design.md
 */
class UnitBoardService
{
    public function __construct(
        private readonly AttendanceCalculator $calculator,
        private readonly GroupTree $groupTree,
    ) {}

    /**
     * @return array{
     *     counts: array{total: int, inside: int, outside: int, excused: int, unexcused: int},
     *     units: list<array{key: string, title: string, department: string, total: int, inside: int, employees: list<array<string, mixed>>}>
     * }
     */
    public function build(CarbonInterface $now, bool $withFaces): array
    {
        $now = Carbon::instance($now);
        $employees = $this->employees($now, $withFaces);

        // Davomat jadvali bilan bir xil: tungi smenada "bugun" 12 soat siljitiladi.
        $today = [];
        $night = [];
        foreach ($employees as $employee) {
            $night[$employee->id] = $this->isNightShift($employee);
            $today[$employee->id] = $this->calculator->workDate($now, $night[$employee->id]);
        }

        $punches = $this->punches($night, $today, $now);
        $notes = $this->notes($today, $now);

        $counts = ['total' => 0, 'inside' => 0, 'outside' => 0, 'excused' => 0, 'unexcused' => 0];
        $rows = [];

        foreach ($employees as $employee) {
            $day = $punches[$employee->id] ?? null;
            $dayNotes = $notes[$employee->id] ?? [];
            $inside = ($day['last_type'] ?? null) === Report::TYPE_ENTRY;
            $in = isset($day['first_entry']) ? date('H:i', $day['first_entry']) : null;
            $excused = $dayNotes !== [];
            $unexcused = $in === null && ! $excused && Carbon::parse($today[$employee->id])->isWeekday();

            $counts['total']++;
            $counts[$inside ? 'inside' : 'outside']++;
            $counts['excused'] += $excused ? 1 : 0;
            $counts['unexcused'] += $unexcused ? 1 : 0;

            $rows[] = [
                'employee' => $employee,
                'public' => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'position' => $employee->position,
                    'group' => null,
                    'inside' => $inside,
                    'excused' => $excused,
                    'unexcused' => $unexcused,
                    'in' => $in,
                    // Chiqqan vaqti faqat bugun kelib, hozir binoda bo‘lmaganda ma’noli.
                    'out' => ! $inside && $in !== null && isset($day['last_exit']) ? date('H:i', $day['last_exit']) : null,
                    'notes' => $dayNotes,
                    'face_url' => $withFaces
                        ? EmployeeResource::faceThumbnailUrl($employee->id, $employee->face_max_updated_at)
                        : null,
                ],
            ];
        }

        return [
            'counts' => $counts,
            'units' => $this->units($rows),
        ];
    }

    /**
     * Amal qilish muddati hozir ichida bo‘lgan xodimlar. JSHSHIR (`card`) atayin olinmaydi.
     *
     * @return Collection<int, Employee>
     */
    private function employees(Carbon $now, bool $withFaces): Collection
    {
        // select() withMax() dan oldin: aks holda withMax `employees.*` ni tanlaydi va JSHSHIR ham o‘qiladi.
        $query = Employee::query()
            ->select(['id', 'name', 'position', 'department_id', 'group_id', 'schedule_id'])
            ->where('valid_from_dt', '<=', $now)
            ->where('valid_to_dt', '>=', $now)
            ->with(['schedule', 'department:id,name'])
            ->orderBy('name')
            ->orderBy('id');

        if ($withFaces) {
            $query->withMax('face', 'updated_at');
        }

        return $query->get();
    }

    private function isNightShift(Employee $employee): bool
    {
        $enter = $employee->schedule?->enter_time?->format('H:i:s');
        $exit = $employee->schedule?->exit_time?->format('H:i:s');

        return $enter !== null && $exit !== null && $this->calculator->isNightShift($enter, $exit);
    }

    /**
     * Har xodimning bugungi ish kunidagi birinchi kirishi, oxirgi chiqishi va oxirgi o‘tish turi.
     * Tungi smena kechagi kunduzidan boshlanishi mumkin — kechagi kun ham olinadi.
     *
     * @param  array<int, bool>  $night
     * @param  array<int, string>  $today
     * @return array<int, array{first_entry: ?int, last_exit: ?int, last_type: ?int}>
     */
    private function punches(array $night, array $today, Carbon $now): array
    {
        if ($today === []) {
            return [];
        }

        $reports = Report::query()
            ->whereIn('employee_id', array_keys($today))
            ->where('occurred_at', '>=', $now->copy()->subDay()->startOfDay()->format('Y-m-d H:i:s'))
            // Yuqori chegara `now` emas: soati oldinda yuradigan terminal o‘tishi ham bugunga tushadi (Davomatdagidek).
            ->where('occurred_at', '<', $now->copy()->addDay()->startOfDay()->addHours(12)->format('Y-m-d H:i:s'))
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->toBase()
            ->get(['employee_id', 'type', 'occurred_at']);

        $map = [];
        foreach ($reports as $report) {
            $employeeId = (int) $report->employee_id;
            $timestamp = (int) strtotime((string) $report->occurred_at);
            // AttendanceCalculator::workDate() bilan bir xil: tungi smenada vaqt 12 soat orqaga suriladi.
            $date = date('Y-m-d', $night[$employeeId] ? $timestamp - 43200 : $timestamp);

            if ($date !== $today[$employeeId]) {
                continue;
            }

            $type = (int) $report->type;
            $day = $map[$employeeId] ?? ['first_entry' => null, 'last_exit' => null, 'last_type' => null];
            if ($type === Report::TYPE_ENTRY) {
                $day['first_entry'] ??= $timestamp;
            } elseif ($type === Report::TYPE_EXIT) {
                $day['last_exit'] = $timestamp;
            }
            $day['last_type'] = $type;
            $map[$employeeId] = $day;
        }

        return $map;
    }

    /**
     * Bugungi ish kuni sanasidagi izohlar: turi va matni (sabab). Matn izohda majburiy.
     *
     * @param  array<int, string>  $today
     * @return array<int, list<array{type: string, text: string}>>
     */
    private function notes(array $today, Carbon $now): array
    {
        if ($today === []) {
            return [];
        }

        $notes = Note::query()
            ->whereIn('employee_id', array_keys($today))
            ->whereDate('dt', '>=', $now->copy()->subDay()->toDateString())
            ->whereDate('dt', '<=', $now->toDateString())
            ->orderBy('id')
            ->get(['employee_id', 'type', 'dt', 'note']);

        $map = [];
        foreach ($notes as $note) {
            if ($note->dt?->toDateString() !== $today[$note->employee_id]) {
                continue;
            }

            $map[$note->employee_id][] = [
                'type' => $note->type->label(),
                'text' => trim((string) $note->note),
            ];
        }

        return $map;
    }

    /**
     * Bo‘limlar nomi bo‘yicha; bo‘lim ichida guruhlar daraxti tartibida, guruhsizlar oxirida.
     * Kartochkada avval binodagilar, keyin qolganlar (ism tartibi saqlanadi).
     *
     * @param  list<array{employee: Employee, public: array<string, mixed>}>  $rows
     * @return list<array<string, mixed>>
     */
    private function units(array $rows): array
    {
        $byDepartment = collect($rows)
            ->groupBy(fn (array $row): int => (int) $row['employee']->department_id)
            ->sortBy(fn ($departmentRows): string => (string) $departmentRows->first()['employee']->department?->name);

        $units = [];
        foreach ($byDepartment as $departmentId => $departmentRows) {
            $order = $this->groupTree->order($departmentId);
            $departmentName = (string) $departmentRows->first()['employee']->department?->name;
            $buckets = [];

            foreach ($departmentRows as $row) {
                $groupId = $row['employee']->group_id;
                // Daraxtda topilmagan guruh (masalan boshqa bo‘lim guruhi) — guruhsiz.
                $key = $groupId !== null && isset($order[$groupId]) ? 'group:'.$groupId : 'ungrouped:'.$departmentId;
                $row['public']['group'] = $groupId !== null && isset($order[$groupId]) ? $order[$groupId]['path'] : null;
                $buckets[$key][] = $row['public'];
            }

            uksort($buckets, fn (string $a, string $b): int => $this->unitIndex($a, $order) <=> $this->unitIndex($b, $order));

            foreach ($buckets as $key => $employees) {
                usort($employees, fn (array $a, array $b): int => $b['inside'] <=> $a['inside']);

                $units[] = [
                    'key' => $key,
                    'title' => $employees[0]['group'] ?? 'Guruhsiz',
                    'department' => $departmentName,
                    'total' => count($employees),
                    'inside' => count(array_filter($employees, fn (array $employee): bool => $employee['inside'])),
                    'employees' => $employees,
                ];
            }
        }

        return $units;
    }

    /**
     * @param  array<int, array{index: int, path: string}>  $order
     */
    private function unitIndex(string $key, array $order): int
    {
        if (str_starts_with($key, 'group:')) {
            return $order[(int) substr($key, 6)]['index'];
        }

        return PHP_INT_MAX;
    }
}
