<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Report;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Kirganlar ro‘yxati": tanlangan kunlarda kamida bir marta kirgan odamlar (tadbir mehmonlari ham)
 * va har kungi birinchi kirish vaqti. Bo‘lim cheklovi (global scope) amal qiladi.
 */
class EntrantListService
{
    /** Bir so‘rovda ko‘pi bilan shuncha kun. */
    public const MAX_DAYS = 31;

    /**
     * @param  array{from: string, to: string, search?: ?string, group_id?: ?int}  $filters
     */
    public function paginate(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        $paginator = $this->employees($filters)->paginate($perPage)->withQueryString();

        $paginator->setCollection($this->rows($paginator->getCollection(), $filters));

        return $paginator;
    }

    /**
     * Excel uchun — hamma qatorlar.
     *
     * @param  array{from: string, to: string, search?: ?string, group_id?: ?int}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function all(array $filters): Collection
    {
        $rows = collect();

        $this->employees($filters)->chunk(1000, function (Collection $employees) use ($filters, $rows): void {
            $rows->push(...$this->rows($employees, $filters));
        });

        return $rows;
    }

    /**
     * Har kuni nechta takrorlanmas odam kirgan: `Y-m-d => son` (kirmagan kun — 0).
     *
     * @param  array{from: string, to: string, search?: ?string, group_id?: ?int}  $filters
     * @return array<string, int>
     */
    public function dailyTotals(array $filters): array
    {
        $counts = $this->entries($filters)
            ->whereIn('employee_id', $this->employees($filters)->reorder()->select('employees.id'))
            ->selectRaw('date(occurred_at) as day, count(distinct employee_id) as total')
            ->groupBy(DB::raw('date(occurred_at)'))
            ->pluck('total', 'day');

        return collect($this->days($filters))
            ->mapWithKeys(fn (string $day): array => [$day => (int) ($counts[$day] ?? 0)])
            ->all();
    }

    /**
     * @param  array{from: string, to: string}  $filters
     * @return list<string>
     */
    public function days(array $filters): array
    {
        return collect(CarbonPeriod::create($filters['from'], $filters['to']))
            ->map(fn (CarbonInterface $day): string => $day->toDateString())
            ->values()
            ->all();
    }

    /**
     * @param  array{from: string, to: string, search?: ?string, group_id?: ?int}  $filters
     * @return Builder<Employee>
     */
    private function employees(array $filters): Builder
    {
        $range = $this->range($filters);
        $search = trim((string) ($filters['search'] ?? ''));

        return Employee::query()
            ->withVisitors()
            ->whereHas('reports', fn ($query) => $query->withVisitors()->entry()->whereBetween('occurred_at', $range))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('card', 'like', '%'.$search.'%')))
            ->when($filters['group_id'] ?? null, fn (Builder $query, $groupId) => $query->where('group_id', $groupId))
            ->with(['group:id,name', 'department:id,name'])
            ->orderBy('name')
            ->orderBy('id');
    }

    /**
     * @param  array{from: string, to: string}  $filters
     * @return Builder<Report>
     */
    private function entries(array $filters): Builder
    {
        return Report::query()
            ->withVisitors()
            ->entry()
            ->whereNotNull('employee_id')
            ->whereBetween('occurred_at', $this->range($filters));
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @param  array{from: string, to: string}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(Collection $employees, array $filters): Collection
    {
        $byEmployee = $employees->isEmpty()
            ? collect()
            : $this->entries($filters)
                ->whereIn('employee_id', $employees->modelKeys())
                ->selectRaw('employee_id, date(occurred_at) as day, min(occurred_at) as first_at, count(*) as passes')
                ->groupBy('employee_id', DB::raw('date(occurred_at)'))
                ->toBase()
                ->get()
                ->groupBy('employee_id');

        $days = $this->days($filters);

        return $employees->map(function (Employee $employee) use ($byEmployee, $days): array {
            $visits = collect($byEmployee->get($employee->id, []))->keyBy('day');

            return [
                'id' => $employee->id,
                'name' => $employee->name,
                'card' => $employee->card,
                'position' => $employee->position,
                'group_name' => $employee->group?->name,
                'department_name' => $employee->department?->name,
                'days' => collect($days)
                    ->mapWithKeys(fn (string $day): array => [
                        $day => isset($visits[$day]) ? Carbon::parse($visits[$day]->first_at)->format('H:i') : null,
                    ])
                    ->all(),
                'days_count' => $visits->count(),
                'passes' => (int) $visits->sum('passes'),
            ];
        })->values();
    }

    /**
     * @param  array{from: string, to: string}  $filters
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function range(array $filters): array
    {
        return [Carbon::parse($filters['from'])->startOfDay(), Carbon::parse($filters['to'])->endOfDay()];
    }
}
