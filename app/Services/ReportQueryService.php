<?php

namespace App\Services;

use App\Enums\AttendanceReportType;
use App\Enums\NoteType;
use App\Models\Employee;
use App\Models\Note;
use App\Models\Report;
use App\Services\Reports\AttendanceCalculator;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

class ReportQueryService
{
    public function __construct(
        private readonly AttendanceCalculator $calculator,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{key: string, label: string}>
     */
    public function columns(array $filters): array
    {
        $type = $this->resolveType($filters);

        return match ($type) {
            AttendanceReportType::Late => [
                ['key' => 'employee_name', 'label' => 'Ism'],
                ['key' => 'group_name', 'label' => 'Guruh'],
                ['key' => 'position', 'label' => 'Lavozim'],
                ['key' => 'date', 'label' => 'Sana'],
                ['key' => 'time', 'label' => 'Vaqt'],
                ['key' => 'late', 'label' => 'Kechikish'],
                ['key' => 'reason', 'label' => 'Sabab'],
            ],
            AttendanceReportType::LeftEarly => [
                ['key' => 'employee_name', 'label' => 'Ism'],
                ['key' => 'group_name', 'label' => 'Guruh'],
                ['key' => 'position', 'label' => 'Lavozim'],
                ['key' => 'date', 'label' => 'Sana'],
                ['key' => 'time', 'label' => 'Vaqt'],
                ['key' => 'early', 'label' => 'Erta'],
                ['key' => 'reason', 'label' => 'Sabab'],
            ],
            AttendanceReportType::Present => [
                ['key' => 'employee_name', 'label' => 'Ism'],
                ['key' => 'group_name', 'label' => 'Guruh'],
                ['key' => 'position', 'label' => 'Lavozim'],
                ['key' => 'date', 'label' => 'Sana'],
            ],
            AttendanceReportType::Absent => [
                ['key' => 'employee_name', 'label' => 'Ism'],
                ['key' => 'group_name', 'label' => 'Guruh'],
                ['key' => 'position', 'label' => 'Lavozim'],
                ['key' => 'date', 'label' => 'Sana'],
                ['key' => 'reason', 'label' => 'Sabab'],
            ],
            AttendanceReportType::WorkTime => $this->workTimeColumns($filters),
            AttendanceReportType::Individual => [
                ['key' => 'employee_name', 'label' => 'Ism'],
                ['key' => 'group_name', 'label' => 'Guruh'],
                ['key' => 'position', 'label' => 'Lavozim'],
                ['key' => 'date', 'label' => 'Sana'],
                ['key' => 'check_in', 'label' => 'Kirish'],
                ['key' => 'check_out', 'label' => 'Chiqish'],
            ],
            default => [
                ['key' => 'employee_name', 'label' => 'Ism'],
                ['key' => 'group_name', 'label' => 'Guruh'],
                ['key' => 'position', 'label' => 'Lavozim'],
                ['key' => 'device_name', 'label' => 'Qurilma'],
                ['key' => 'type_label', 'label' => 'Tur'],
                ['key' => 'occurred_at', 'label' => 'Sana va vaqt'],
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $type = $this->resolveType($filters);

        if ($type === null || $type === AttendanceReportType::Entry || $type === AttendanceReportType::Exit) {
            return $this->paginateRaw($filters, $perPage);
        }

        $rows = $this->buildComputedRows($filters, $type);
        $page = max(1, (int) request()->input('page', 1));
        $slice = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return new Paginator($slice, $rows->count(), $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'query' => request()->query(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function forExport(array $filters): Collection
    {
        $type = $this->resolveType($filters);

        if ($type === null || $type === AttendanceReportType::Entry || $type === AttendanceReportType::Exit) {
            return $this->rawQuery($filters)
                ->get()
                ->map(fn (Report $report): array => $this->mapRawReport($report))
                ->values();
        }

        return $this->buildComputedRows($filters, $type);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{key: string, label: string}>
     */
    private function workTimeColumns(array $filters): array
    {
        [$from, $to] = $this->resolveDateBounds($filters, AttendanceReportType::WorkTime);

        $columns = [
            ['key' => 'employee_name', 'label' => 'Ism'],
            ['key' => 'group_name', 'label' => 'Guruh'],
            ['key' => 'position', 'label' => 'Lavozim'],
        ];

        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
            $key = $day->toDateString();
            $columns[] = ['key' => $key, 'label' => $key];
        }

        $columns[] = ['key' => 'total', 'label' => 'Jami'];

        return $columns;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    public function resolveDateBounds(array $filters, AttendanceReportType $type): array
    {
        $from = ! empty($filters['date_from'])
            ? Carbon::parse($filters['date_from'])
            : now()->startOfDay();
        $to = ! empty($filters['date_to'])
            ? Carbon::parse($filters['date_to'])
            : now()->endOfDay();

        if (! $type->requiresBoundedDates() && empty($filters['date_from']) && empty($filters['date_to'])) {
            return [$from, $to];
        }

        if (empty($filters['date_from'])) {
            $from = now()->startOfDay();
        }
        if (empty($filters['date_to'])) {
            $to = now()->endOfDay();
        }

        return [$from, $to];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function buildComputedRows(array $filters, AttendanceReportType $type): Collection
    {
        return match ($type) {
            AttendanceReportType::Late => $this->buildLateRows($filters),
            AttendanceReportType::LeftEarly => $this->buildLeftEarlyRows($filters),
            AttendanceReportType::Present => $this->buildPresentRows($filters),
            AttendanceReportType::Absent => $this->buildAbsentRows($filters),
            AttendanceReportType::WorkTime => $this->buildWorkTimeRows($filters),
            AttendanceReportType::Individual => $this->buildIndividualRows($filters),
            default => collect(),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function buildLateRows(array $filters): Collection
    {
        $reports = $this->scheduledPunchReports($filters, Report::TYPE_ENTRY);
        $notes = $this->notesByEmployeeDate($reports->pluck('employee_id')->filter()->all(), NoteType::CameLate);
        $rows = collect();

        foreach ($this->groupPunchesByEmployeeWorkDate($reports) as $group) {
            /** @var Report $first */
            $first = $group['first'];
            $employee = $first->employee;
            $schedule = $employee?->schedule;
            if ($employee === null || $schedule === null) {
                continue;
            }

            $night = $this->calculator->isNightShift(
                (string) $schedule->enter_time?->format('H:i:s'),
                (string) $schedule->exit_time?->format('H:i:s'),
            );
            $late = $this->calculator->lateSeconds(
                $first->occurred_at,
                (string) $schedule->enter_time?->format('H:i:s'),
                $night,
            );

            if ($late <= 0) {
                continue;
            }

            $date = $group['date'];
            $rows->push([
                'employee_name' => $employee->name,
                'group_name' => $employee->group?->name,
                'position' => $employee->position,
                'date' => $date,
                'time' => $first->occurred_at?->format('H:i:s'),
                'late' => $this->formatDuration($late),
                'reason' => $notes[$employee->id.'|'.$date] ?? null,
            ]);
        }

        return $rows->sortBy(['date', 'employee_name'])->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function buildLeftEarlyRows(array $filters): Collection
    {
        $reports = $this->scheduledPunchReports($filters, Report::TYPE_EXIT);
        $notes = $this->notesByEmployeeDate($reports->pluck('employee_id')->filter()->all(), NoteType::LeftEarly);
        $rows = collect();

        foreach ($this->groupPunchesByEmployeeWorkDate($reports) as $group) {
            /** @var Report $last */
            $last = $group['last'];
            $employee = $last->employee;
            $schedule = $employee?->schedule;
            if ($employee === null || $schedule === null) {
                continue;
            }

            $night = $this->calculator->isNightShift(
                (string) $schedule->enter_time?->format('H:i:s'),
                (string) $schedule->exit_time?->format('H:i:s'),
            );
            $early = $this->calculator->earlySeconds(
                $last->occurred_at,
                (string) $schedule->exit_time?->format('H:i:s'),
                $night,
            );

            if ($early <= 0) {
                continue;
            }

            $date = $group['date'];
            $rows->push([
                'employee_name' => $employee->name,
                'group_name' => $employee->group?->name,
                'position' => $employee->position,
                'date' => $date,
                'time' => $last->occurred_at?->format('H:i:s'),
                'early' => $this->formatDuration($early),
                'reason' => $notes[$employee->id.'|'.$date] ?? null,
            ]);
        }

        return $rows->sortBy(['date', 'employee_name'])->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function buildPresentRows(array $filters): Collection
    {
        [$from, $to] = $this->resolveDateBounds($filters, AttendanceReportType::Present);
        $employees = $this->filteredEmployees($filters);
        $punchedDates = $this->employeePunchDates($filters, $from, $to);
        $rows = collect();

        foreach ($this->eachDate($from, $to) as $date) {
            foreach ($employees as $employee) {
                if (! isset($punchedDates[$employee->id][$date])) {
                    continue;
                }

                $rows->push([
                    'employee_name' => $employee->name,
                    'group_name' => $employee->group?->name,
                    'position' => $employee->position,
                    'date' => $date,
                ]);
            }
        }

        return $rows->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function buildAbsentRows(array $filters): Collection
    {
        [$from, $to] = $this->resolveDateBounds($filters, AttendanceReportType::Absent);
        $employees = $this->filteredEmployees($filters);
        $punchedDates = $this->employeePunchDates($filters, $from, $to);
        $notes = $this->notesByEmployeeDate($employees->pluck('id')->all(), NoteType::Absent);
        $rows = collect();

        foreach ($this->eachDate($from, $to) as $date) {
            foreach ($employees as $employee) {
                if (isset($punchedDates[$employee->id][$date])) {
                    continue;
                }

                $rows->push([
                    'employee_name' => $employee->name,
                    'group_name' => $employee->group?->name,
                    'position' => $employee->position,
                    'date' => $date,
                    'reason' => $notes[$employee->id.'|'.$date] ?? null,
                ]);
            }
        }

        return $rows->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function buildIndividualRows(array $filters): Collection
    {
        $reports = $this->scheduledPunchReports($filters, null);
        $rows = collect();

        foreach ($this->groupPunchesByEmployeeWorkDate($reports) as $group) {
            /** @var Report $firstEntry */
            $firstEntry = $group['first_entry'];
            /** @var Report|null $lastExit */
            $lastExit = $group['last_exit'];
            $employee = ($firstEntry ?? $lastExit)?->employee;
            if ($employee === null) {
                continue;
            }

            $rows->push([
                'employee_name' => $employee->name,
                'group_name' => $employee->group?->name,
                'position' => $employee->position,
                'date' => $group['date'],
                'check_in' => $firstEntry?->occurred_at?->format('Y-m-d H:i:s') ?? '—',
                'check_out' => $lastExit?->occurred_at?->format('Y-m-d H:i:s') ?? '—',
            ]);
        }

        return $rows->sortBy(['date', 'employee_name'])->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function buildWorkTimeRows(array $filters): Collection
    {
        [$from, $to] = $this->resolveDateBounds($filters, AttendanceReportType::WorkTime);
        $dates = $this->eachDate($from, $to);
        $reports = $this->scheduledPunchReports($filters, null);
        $grouped = $this->groupPunchesByEmployeeWorkDate($reports);

        /** @var array<int, array<string, mixed>> $byEmployee */
        $byEmployee = [];

        foreach ($grouped as $group) {
            $employee = ($group['first_entry'] ?? $group['last_exit'])?->employee;
            if ($employee === null) {
                continue;
            }

            $id = $employee->id;
            if (! isset($byEmployee[$id])) {
                $byEmployee[$id] = [
                    'employee_name' => $employee->name,
                    'group_name' => $employee->group?->name,
                    'position' => $employee->position,
                    'total_seconds' => 0,
                ];
                foreach ($dates as $date) {
                    $byEmployee[$id][$date] = '—';
                }
            }

            $seconds = $this->calculator->workedSeconds(
                $group['first_entry']?->occurred_at,
                $group['last_exit']?->occurred_at,
            );
            $date = $group['date'];
            if (array_key_exists($date, $byEmployee[$id])) {
                $byEmployee[$id][$date] = $seconds > 0 ? $this->formatDuration($seconds) : '—';
                $byEmployee[$id]['total_seconds'] += $seconds;
            }
        }

        return collect($byEmployee)
            ->map(function (array $row): array {
                $row['total'] = $this->formatDuration((int) $row['total_seconds']);
                unset($row['total_seconds']);

                return $row;
            })
            ->sortBy('employee_name')
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Report>
     */
    private function scheduledPunchReports(array $filters, ?int $punchType): Collection
    {
        $query = Report::query()
            ->with(['employee.group:id,name', 'employee.schedule'])
            ->whereNotNull('employee_id')
            ->whereHas('employee', fn (Builder $q) => $q->whereNotNull('schedule_id'))
            ->orderBy('occurred_at');

        $this->applyCommonReportFilters($query, $filters);

        if ($punchType !== null) {
            $query->where('type', $punchType);
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, Report>  $reports
     * @return list<array{date: string, first: Report, last: Report, first_entry: ?Report, last_exit: ?Report}>
     */
    private function groupPunchesByEmployeeWorkDate(Collection $reports): array
    {
        /** @var array<string, array{date: string, punches: list<Report>}> $buckets */
        $buckets = [];

        foreach ($reports as $report) {
            $employee = $report->employee;
            $schedule = $employee?->schedule;
            if ($employee === null || $schedule === null || $report->occurred_at === null) {
                continue;
            }

            $night = $this->calculator->isNightShift(
                (string) $schedule->enter_time?->format('H:i:s'),
                (string) $schedule->exit_time?->format('H:i:s'),
            );
            $date = $this->calculator->workDate($report->occurred_at, $night);
            $key = $employee->id.'|'.$date;

            if (! isset($buckets[$key])) {
                $buckets[$key] = ['date' => $date, 'punches' => []];
            }
            $buckets[$key]['punches'][] = $report;
        }

        $groups = [];
        foreach ($buckets as $bucket) {
            $punches = $bucket['punches'];
            $entries = array_values(array_filter($punches, fn (Report $r) => $r->type === Report::TYPE_ENTRY));
            $exits = array_values(array_filter($punches, fn (Report $r) => $r->type === Report::TYPE_EXIT));

            $groups[] = [
                'date' => $bucket['date'],
                'first' => $punches[0],
                'last' => $punches[array_key_last($punches)],
                'first_entry' => $entries[0] ?? null,
                'last_exit' => $exits === [] ? null : $exits[array_key_last($exits)],
            ];
        }

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, \App\Models\Employee>
     */
    private function filteredEmployees(array $filters): Collection
    {
        $query = Employee::query()->with('group:id,name')->orderBy('name');

        if (! empty($filters['employee_id'])) {
            $query->where('id', $filters['employee_id']);
        }

        $departmentIds = array_values(array_unique(array_filter(array_map(
            'intval',
            $filters['department_ids'] ?? [],
        ))));
        $groupIds = array_values(array_unique(array_filter(array_map(
            'intval',
            $filters['group_ids'] ?? [],
        ))));

        if ($departmentIds !== []) {
            $query->whereIn('department_id', $departmentIds);
        }
        if ($groupIds !== []) {
            $query->whereIn('group_id', $groupIds);
        }
        if (! empty($filters['schedule_id'])) {
            $query->where('schedule_id', $filters['schedule_id']);
        }

        return $query->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, true>>
     */
    private function employeePunchDates(array $filters, Carbon $from, Carbon $to): array
    {
        $query = Report::query()
            ->whereNotNull('employee_id')
            ->whereBetween('occurred_at', [
                $from->format('Y-m-d H:i:s'),
                $to->format('Y-m-d H:i:s'),
            ]);

        $this->applyCommonReportFilters($query, array_merge($filters, [
            'date_from' => $from->format('Y-m-d H:i:s'),
            'date_to' => $to->format('Y-m-d H:i:s'),
        ]));

        $map = [];
        foreach ($query->get(['employee_id', 'occurred_at']) as $report) {
            if ($report->employee_id === null || $report->occurred_at === null) {
                continue;
            }
            $date = $report->occurred_at->toDateString();
            $map[$report->employee_id][$date] = true;
        }

        return $map;
    }

    /**
     * @param  list<int>  $employeeIds
     * @return array<string, string>
     */
    private function notesByEmployeeDate(array $employeeIds, NoteType $type): array
    {
        $employeeIds = array_values(array_unique(array_filter($employeeIds)));
        if ($employeeIds === []) {
            return [];
        }

        $map = [];
        foreach (Note::query()->whereIn('employee_id', $employeeIds)->where('type', $type)->get() as $note) {
            $map[$note->employee_id.'|'.$note->dt?->toDateString()] = (string) $note->note;
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    private function eachDate(Carbon $from, Carbon $to): array
    {
        $dates = [];
        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        return $dates;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function paginateRaw(array $filters, int $perPage): LengthAwarePaginator
    {
        $paginator = $this->rawQuery($filters)
            ->paginate($perPage)
            ->withQueryString();

        $paginator->getCollection()->transform(
            fn (Report $report): array => $this->mapRawReport($report),
        );

        return $paginator;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Report>
     */
    private function rawQuery(array $filters): Builder
    {
        $query = Report::query()
            ->with(['employee.group:id,name', 'device:id,name'])
            ->latest('occurred_at');

        $this->applyCommonReportFilters($query, $filters);

        $type = $this->resolveType($filters);
        if ($type === AttendanceReportType::Entry || $type === AttendanceReportType::Exit) {
            $query->where('type', $type->value);
        }

        return $query;
    }

    /**
     * @param  Builder<Report>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyCommonReportFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        $departmentIds = array_values(array_unique(array_filter(array_map(
            'intval',
            $filters['department_ids'] ?? [],
        ))));
        $groupIds = array_values(array_unique(array_filter(array_map(
            'intval',
            $filters['group_ids'] ?? [],
        ))));

        if ($departmentIds !== [] || $groupIds !== []) {
            $query->where(function (Builder $builder) use ($departmentIds, $groupIds): void {
                if ($departmentIds !== []) {
                    $builder->whereIn('department_id', $departmentIds);
                }

                if ($groupIds !== []) {
                    $method = $departmentIds !== [] ? 'orWhereHas' : 'whereHas';
                    $builder->{$method}(
                        'employee',
                        fn (Builder $employeeQuery) => $employeeQuery->whereIn('group_id', $groupIds),
                    );
                }
            });
        }

        if (! empty($filters['device_id'])) {
            $query->where('device_id', $filters['device_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->whereHas('device', fn ($deviceQuery) => $deviceQuery->where('category_id', $filters['category_id']));
        }

        if (! empty($filters['schedule_id'])) {
            $query->whereHas('employee', fn ($employeeQuery) => $employeeQuery->where('schedule_id', $filters['schedule_id']));
        }

        if (! empty($filters['date_from'])) {
            $query->where('occurred_at', '>=', Carbon::parse($filters['date_from'])->format('Y-m-d H:i:s'));
        }

        if (! empty($filters['date_to'])) {
            $to = Carbon::parse($filters['date_to']);
            if ($this->isDateOnly((string) $filters['date_to'])) {
                $to = $to->endOfDay();
            }
            $query->where('occurred_at', '<=', $to->format('Y-m-d H:i:s'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRawReport(Report $report): array
    {
        return [
            'id' => $report->id,
            'employee_name' => $report->employee_name,
            'group_name' => $report->employee?->group?->name,
            'position' => $report->employee?->position,
            'device_name' => $report->device?->name,
            'type' => $report->type,
            'type_label' => $report->type_label,
            'occurred_at' => $report->occurred_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function resolveType(array $filters): ?AttendanceReportType
    {
        if (! isset($filters['type']) || $filters['type'] === '' || $filters['type'] === null) {
            return null;
        }

        return AttendanceReportType::tryFrom((int) $filters['type']);
    }

    private function isDateOnly(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }

    public function formatDuration(int $seconds): string
    {
        $seconds = max(0, $seconds);

        return sprintf(
            '%02d:%02d:%02d',
            intdiv($seconds, 3600),
            intdiv($seconds % 3600, 60),
            $seconds % 60,
        );
    }
}
