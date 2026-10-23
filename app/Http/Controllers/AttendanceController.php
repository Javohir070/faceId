<?php

namespace App\Http\Controllers;

use App\Enums\NoteType;
use App\Exports\AttendanceExport;
use App\Http\Requests\StoreNoteRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Group;
use App\Models\Note;
use App\Services\Attendance\AttendanceGridService;
use App\Services\Attendance\AttendancePeriod;
use App\Services\NoteService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttendanceController extends Controller
{
    private const PER_PAGE = 50;

    public function __construct(
        private readonly AttendanceGridService $grid,
        private readonly NoteService $noteService,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate($this->filterRules());
        $now = now();
        $period = AttendancePeriod::make($filters['period'] ?? '2weeks', Carbon::parse($filters['date'] ?? $now->toDateString()));

        // Jadval bir so‘rovda bir marta hisoblanadi; qisman qayta yuklashda (masalan faqat `day`) umuman hisoblanmaydi.
        $result = null;
        $build = function () use (&$result, $filters, $period, $now): array {
            return $result ??= $this->grid->build($this->employees($filters), $period, $now);
        };

        return Inertia::render('Admin/Attendance/Index', [
            'period' => $period->toArray(),
            'days' => $period->columns($now),
            'filters' => [
                'period' => $period->type,
                'date' => $period->anchor->toDateString(),
                'department_id' => isset($filters['department_id']) ? (int) $filters['department_id'] : null,
                'group_id' => isset($filters['group_id']) ? (int) $filters['group_id'] : null,
                'search' => $filters['search'] ?? null,
                'tab' => $filters['tab'] ?? 'attendance',
            ],
            'stats' => fn () => $build()['stats'],
            'rows' => fn () => $this->paginateRows($build()['rows'], $request),
            'department_counts' => fn () => collect($build()['rows'])->countBy(fn (array $row): int => $row['department']['id'])->all(),
            'day' => fn () => $this->dayDetail($filters, $now),
            'notes' => fn () => ($filters['tab'] ?? null) === 'notes' ? $this->notes($filters, $period) : null,
            'departments' => fn () => Department::query()->visibleTo($request->user())->orderBy('name')->get(['id', 'name']),
            'groups' => fn () => Group::query()->orderBy('name')->get(['id', 'name', 'department_id']),
            'employees' => fn () => Employee::query()->orderBy('name')->get(['id', 'name']),
            'types' => array_map(
                fn (NoteType $type): array => ['value' => $type->value, 'label' => $type->label()],
                NoteType::cases(),
            ),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $filters = $request->validate($this->filterRules());
        $now = now();
        $period = AttendancePeriod::make($filters['period'] ?? '2weeks', Carbon::parse($filters['date'] ?? $now->toDateString()));
        $range = $period->toArray();

        return Excel::download(
            new AttendanceExport(
                collect($this->grid->build($this->employees($filters), $period, $now)['rows']),
                $period->columns($now),
            ),
            'davomat_'.$range['start'].'_'.$range['end'].'.xlsx',
        );
    }

    public function storeNote(StoreNoteRequest $request): RedirectResponse
    {
        $this->noteService->create($request->validated());

        return back()->with('success', 'Izoh muvaffaqiyatli yaratildi.');
    }

    /**
     * @return array<string, list<string>>
     */
    private function filterRules(): array
    {
        return [
            'period' => ['nullable', 'in:'.implode(',', AttendancePeriod::TYPES)],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'department_id' => ['nullable', 'integer'],
            'group_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
            'tab' => ['nullable', 'in:attendance,notes'],
            'day_employee_id' => ['nullable', 'integer'],
            'day_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * Filtrga mos xodimlar: bo‘lim nomi, so‘ng ism bo‘yicha. Bo‘lim cheklovi global scope orqali.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Employee>
     */
    private function employees(array $filters): Collection
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return Employee::query()
            ->select('employees.*')
            ->join('departments', 'departments.id', '=', 'employees.department_id')
            ->with(['schedule', 'department:id,name'])
            ->when(! empty($filters['department_id']), fn (Builder $q) => $q->where('employees.department_id', $filters['department_id']))
            ->when(! empty($filters['group_id']), fn (Builder $q) => $q->where('employees.group_id', $filters['group_id']))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('employees.name', 'like', '%'.$search.'%')
                ->orWhere('employees.card', 'like', '%'.$search.'%')))
            ->orderBy('departments.name')
            ->orderBy('employees.name')
            ->get();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function paginateRows(array $rows, Request $request): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query('page', 1));

        return new LengthAwarePaginator(
            array_slice($rows, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            count($rows),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->except('page')],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>|null
     */
    private function dayDetail(array $filters, Carbon $now): ?array
    {
        if (empty($filters['day_employee_id']) || empty($filters['day_date'])) {
            return null;
        }

        $employee = Employee::query()->find($filters['day_employee_id']);

        return $employee === null ? null : $this->grid->day($employee, $filters['day_date'], $now);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function notes(array $filters, AttendancePeriod $period): mixed
    {
        $range = $period->toArray();

        return $this->noteService
            ->paginate(12, [
                'date_from' => $range['start'],
                'date_to' => $range['end'],
                'search' => $filters['search'] ?? null,
            ])
            ->withQueryString()
            ->through(fn (Note $note): array => [
                'id' => $note->id,
                'employee' => $note->employee?->only(['id', 'name']),
                'type' => $note->type->value,
                'type_label' => $note->type->label(),
                'dt' => $note->dt?->toDateString(),
                'note' => (string) $note->note,
            ]);
    }
}
