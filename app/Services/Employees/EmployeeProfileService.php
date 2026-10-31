<?php

namespace App\Services\Employees;

use App\Models\Device;
use App\Models\Employee;
use App\Models\Note;
use App\Models\Report;
use App\Services\Attendance\AttendanceGridService;
use App\Services\Attendance\AttendancePeriod;
use App\Services\Reports\AttendanceCalculator;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Xodim sahifasi: oy kalendari (holatlar Davomat qoidalari bilan), kirish-chiqishlar ro‘yxati va filtri.
 * Faqat bazadan o‘qiydi.
 */
class EmployeeProfileService
{
    public const PASSES_PER_PAGE = 20;

    public function __construct(
        private readonly AttendanceGridService $grid,
        private readonly AttendanceCalculator $calculator,
    ) {}

    /**
     * Oyning har bir kuni (dam olish kunlari ham): holat, kelgan/ketgan vaqt, kirish/chiqishlar soni, ruxsatlar.
     *
     * @return array{month: string, prev: string, next: string, days: list<array<string, mixed>>, stats: array<string, mixed>}
     */
    public function month(Employee $employee, CarbonImmutable $month, CarbonInterface $now): array
    {
        $employee->loadMissing('schedule');
        $period = AttendancePeriod::make('month', $month);
        $grid = $this->grid->build(EloquentCollection::make([$employee]), $period, $now);
        $cells = collect($grid['rows'][0]['cells'] ?? [])->keyBy('date');
        $passes = $this->passesByWorkDate($employee, $period);
        $notes = Note::query()
            ->where('employee_id', $employee->id)
            ->whereDate('dt', '>=', $period->start->toDateString())
            ->whereDate('dt', '<=', $period->end->toDateString())
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Note $note): string => $note->dt->toDateString());
        $today = $now->toDateString();

        $days = [];
        for ($day = $period->start; $day->lte($period->end); $day = $day->addDay()) {
            $date = $day->toDateString();
            $cell = $cells->get($date);
            $dayPasses = $passes[$date] ?? null;
            $worked = (int) ($cell['worked_seconds'] ?? 0);

            $days[] = [
                'date' => $date,
                'day' => $day->day,
                'weekday' => $day->dayOfWeekIso,
                'is_weekend' => $day->isWeekend(),
                'is_today' => $date === $today,
                // Dam olish kunlari Davomatda hisoblanmaydi; ish kunlari holati jadvaldagi katak bilan bir xil.
                'status' => $cell['status'] ?? ($day->isWeekend() ? 'weekend' : 'inactive'),
                'in' => $cell['in'] ?? $dayPasses['first_in'] ?? null,
                'out' => $cell['out'] ?? $dayPasses['last_out'] ?? null,
                'inside' => (bool) ($cell['inside'] ?? false),
                'early' => (bool) ($cell['early'] ?? false),
                'worked' => $worked > 0 ? AttendanceGridService::formatDuration($worked) : null,
                'entries' => $dayPasses['entries'] ?? 0,
                'exits' => $dayPasses['exits'] ?? 0,
                'notes' => $notes->get($date, collect())
                    ->map(fn (Note $note): string => $note->type->label())
                    ->values()
                    ->all(),
            ];
        }

        return [
            'month' => $month->format('Y-m'),
            'prev' => $month->subMonthNoOverflow()->format('Y-m'),
            'next' => $month->addMonthNoOverflow()->format('Y-m'),
            'days' => $days,
            'stats' => $grid['stats'] + [
                'entries' => array_sum(array_column($passes, 'entries')),
                'exits' => array_sum(array_column($passes, 'exits')),
            ],
        ];
    }

    /**
     * @param  array{from: string, to: string, type: ?string, device_id: ?int}  $filters
     */
    public function passes(Employee $employee, array $filters): LengthAwarePaginator
    {
        return $this->passesQuery($employee, $filters)
            ->when($filters['type'] !== null, fn (Builder $query) => $query->where(
                'type',
                $filters['type'] === 'exit' ? Report::TYPE_EXIT : Report::TYPE_ENTRY,
            ))
            ->with('device.category:id,name')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(self::PASSES_PER_PAGE)
            ->withQueryString()
            ->through(fn (Report $report): array => [
                'id' => $report->id,
                'date' => $report->occurred_at->toDateString(),
                'time' => $report->occurred_at->format('H:i:s'),
                'type' => $report->type === Report::TYPE_EXIT ? 'exit' : 'entry',
                'place' => $report->device === null
                    ? 'Noma’lum qurilma'
                    : implode(' · ', array_filter([$report->device->category?->name, $report->device->name])),
            ]);
    }

    /**
     * Filtrdagi oraliq va qurilma bo‘yicha kirish/chiqishlar soni (tur filtri hisobga olinmaydi).
     *
     * @param  array{from: string, to: string, type: ?string, device_id: ?int}  $filters
     * @return array{entries: int, exits: int}
     */
    public function passesSummary(Employee $employee, array $filters): array
    {
        $counts = $this->passesQuery($employee, $filters)
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return [
            'entries' => (int) ($counts[Report::TYPE_ENTRY] ?? 0),
            'exits' => (int) ($counts[Report::TYPE_EXIT] ?? 0),
        ];
    }

    /**
     * Xodim o‘tgan qurilmalar — kirish-chiqishlar filtri uchun.
     *
     * @return list<array{id: int, name: string}>
     */
    public function passDevices(Employee $employee): array
    {
        return Device::query()
            ->whereIn('id', Report::query()
                ->where('employee_id', $employee->id)
                ->whereNotNull('device_id')
                ->select('device_id')
                ->distinct())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Device $device): array => ['id' => $device->id, 'name' => $device->name])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function day(Employee $employee, string $date, CarbonInterface $now): array
    {
        return $this->grid->day($employee, $date, $now);
    }

    /**
     * @param  array{from: string, to: string, type: ?string, device_id: ?int}  $filters
     * @return Builder<Report>
     */
    private function passesQuery(Employee $employee, array $filters): Builder
    {
        return Report::query()
            ->where('employee_id', $employee->id)
            ->where('occurred_at', '>=', $filters['from'].' 00:00:00')
            ->where('occurred_at', '<', CarbonImmutable::parse($filters['to'])->addDay()->format('Y-m-d H:i:s'))
            ->when($filters['device_id'] !== null, fn (Builder $query) => $query->where('device_id', $filters['device_id']));
    }

    /**
     * Ish kuni bo‘yicha (tungi smenada 12 soat siljitilgan) kirish/chiqishlar soni, birinchi kirish va oxirgi chiqish.
     *
     * @return array<string, array{entries: int, exits: int, first_in: ?string, last_out: ?string}>
     */
    private function passesByWorkDate(Employee $employee, AttendancePeriod $period): array
    {
        $enter = $employee->schedule?->enter_time?->format('H:i:s');
        $exit = $employee->schedule?->exit_time?->format('H:i:s');
        $night = $enter !== null && $exit !== null && $this->calculator->isNightShift($enter, $exit);
        $from = $period->start->toDateString();
        $to = $period->end->toDateString();

        $days = [];
        $reports = Report::query()
            ->where('employee_id', $employee->id)
            ->where('occurred_at', '>=', $period->start->format('Y-m-d H:i:s'))
            ->where('occurred_at', '<', $period->end->addDay()->startOfDay()->addHours(12)->format('Y-m-d H:i:s'))
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get(['id', 'type', 'occurred_at']);

        foreach ($reports as $report) {
            $date = $this->calculator->workDate($report->occurred_at, $night);

            if ($date < $from || $date > $to) {
                continue;
            }

            $days[$date] ??= ['entries' => 0, 'exits' => 0, 'first_in' => null, 'last_out' => null];

            if ($report->type === Report::TYPE_EXIT) {
                $days[$date]['exits']++;
                $days[$date]['last_out'] = $report->occurred_at->format('H:i');
            } else {
                $days[$date]['entries']++;
                $days[$date]['first_in'] ??= $report->occurred_at->format('H:i');
            }
        }

        return $days;
    }
}
