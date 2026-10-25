<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Report;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Zal ekrani ("Tashrif statistikasi") uchun bugungi ko‘rsatkichlar.
 * Hamma son takrorlanmas odamlar bo‘yicha va bo‘lim cheklovi (global scope) orqali hisoblanadi.
 */
class VisitStatsService
{
    public function __construct(
        private readonly LiveMonitorService $liveMonitorService,
        private readonly VisitAdjustmentService $adjustments,
    ) {}

    /**
     * @return array{
     *     registered:int, registered_today:int,
     *     entered:int, entered_last_hour:int, exited:int, exited_last_hour:int,
     *     inside:int, inside_male:int, inside_female:int,
     *     local:int, foreign:int, male:int, female:int,
     *     groups:list<array{id:int, name:string, entered:int}>,
     *     last_arrival:array<string, mixed>|null
     * }
     */
    public function today(): array
    {
        $now = now();
        $today = [$now->copy()->startOfDay(), $now->copy()->endOfDay()];
        $lastHour = [$now->copy()->subHour(), $now->copy()];

        $insideIds = $this->insideEmployeeIds($today);
        $insideByGender = $insideIds === []
            ? collect()
            : Employee::query()
                ->withVisitors()
                ->whereIn('id', $insideIds)
                ->groupBy('gender')
                ->selectRaw('gender, count(*) as total')
                ->pluck('total', 'gender');

        // Admin qo‘lda qo‘shgan kirishlar (mahalliy va guruh qismi LiveMonitorService ichida qo‘shiladi).
        $added = $this->adjustments->totals($today[0]);

        $enteredToday = fn (): Builder => Employee::query()
            ->withVisitors()
            ->whereHas('reports', fn ($query) => $query->withVisitors()->entry()->whereBetween('occurred_at', $today));

        return [
            'registered' => Employee::query()->withVisitors()->count(),
            'registered_today' => Employee::query()->withVisitors()->whereBetween('created_at', $today)->count(),
            'entered' => $this->people(Report::TYPE_ENTRY, $today) + $added['entered'],
            'entered_last_hour' => $this->people(Report::TYPE_ENTRY, $lastHour) + $this->adjustments->enteredBetween(...$lastHour),
            'exited' => $this->people(Report::TYPE_EXIT, $today),
            'exited_last_hour' => $this->people(Report::TYPE_EXIT, $lastHour),
            'inside' => count($insideIds),
            'inside_male' => (int) ($insideByGender[Employee::GENDER_MALE] ?? 0),
            'inside_female' => (int) ($insideByGender[Employee::GENDER_FEMALE] ?? 0),
            ...$this->liveMonitorService->enteredByOrigin($today),
            'male' => $enteredToday()->where('gender', Employee::GENDER_MALE)->count() + $added['male'],
            'female' => $enteredToday()->where('gender', Employee::GENDER_FEMALE)->count() + $added['female'],
            'groups' => $this->liveMonitorService->enteredByGroup($today),
            'last_arrival' => $this->lastArrival($today),
        ];
    }

    /**
     * @param  array{0:CarbonInterface, 1:CarbonInterface}  $range
     */
    private function people(int $type, array $range): int
    {
        return Report::query()
            ->withVisitors()
            ->where('type', $type)
            ->whereBetween('occurred_at', $range)
            ->whereNotNull('employee_id')
            ->distinct()
            ->count('employee_id');
    }

    /**
     * Bugungi oxirgi o‘tishi kirish bo‘lgan xodimlar. Bir vaqtda ikki o‘tish bo‘lsa, keyin yozilgani (katta id) oxirgisi.
     *
     * @param  array{0:CarbonInterface, 1:CarbonInterface}  $range
     * @return list<int>
     */
    private function insideEmployeeIds(array $range): array
    {
        $latest = Report::query()
            ->withVisitors()
            ->whereBetween('occurred_at', $range)
            ->whereNotNull('employee_id')
            ->groupBy('employee_id')
            ->selectRaw('employee_id, max(occurred_at) as last_at');

        $lastPassIds = Report::query()
            ->withVisitors()
            ->joinSub($latest, 'latest', fn ($join) => $join
                ->on('reports.employee_id', '=', 'latest.employee_id')
                ->on('reports.occurred_at', '=', 'latest.last_at'))
            ->groupBy('reports.employee_id')
            ->selectRaw('max(reports.id) as id')
            ->pluck('id');

        return Report::query()
            ->withVisitors()
            ->whereIn('id', $lastPassIds)
            ->where('type', Report::TYPE_ENTRY)
            ->pluck('employee_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  array{0:CarbonInterface, 1:CarbonInterface}  $range
     * @return array<string, mixed>|null
     */
    private function lastArrival(array $range): ?array
    {
        $report = Report::query()
            ->withVisitors()
            ->entry()
            ->whereBetween('occurred_at', $range)
            ->whereNotNull('employee_id')
            ->with([
                'employee' => fn ($query) => $query
                    ->withVisitors()
                    ->select(['id', 'name', 'country', 'position', 'department_id']),
                'employee.department:id,name',
                'device:id,name',
            ])
            ->latest('occurred_at')
            ->latest('id')
            ->first();

        if ($report === null) {
            return null;
        }

        $employee = $report->employee;

        return [
            'employee_name' => $employee?->name ?? $report->employee_name,
            'country' => $employee?->country,
            'country_name' => $employee?->countryName(),
            'position' => $employee?->position,
            'department_name' => $employee?->department?->name,
            'device_name' => $report->device?->name,
            'time' => $report->occurred_at?->format('H:i:s'),
        ];
    }
}
