<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceReportType;
use App\Exports\ReportsExport;
use App\Http\Requests\PullReportEventsRequest;
use App\Models\Category;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Group;
use App\Models\Schedule;
use App\Services\Hikvision\HikvisionEventPullService;
use App\Services\ReportQueryService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly HikvisionEventPullService $eventPullService,
        private readonly ReportQueryService $reportQueryService,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $this->validatedFilters($request);

        return Inertia::render('Admin/Reports/Index', [
            'reports' => $this->reportQueryService->paginate($filters),
            'columns' => $this->reportQueryService->columns($filters),
            'filters' => $this->normalizeFilterPayload($filters),
            'employees' => Employee::query()->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->visibleTo(auth()->user())->orderBy('name')->get(['id', 'name']),
            'groups' => Group::query()->orderBy('name')->get(['id', 'name', 'department_id', 'parent_id']),
            'devices' => Device::query()->ordered()->get(['id', 'name']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'schedules' => Schedule::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function pull(): Response
    {
        $devices = Device::query()
            ->with('category:id,name')
            ->ordered()
            ->get()
            ->map(fn (Device $device): array => [
                'id' => $device->id,
                'name' => $device->name,
                'ip_address' => $device->ip_address,
                'type_label' => $device->typeLabel(),
                'category' => $device->category?->only(['id', 'name']),
                'is_online' => $device->is_online,
                'has_credentials' => $device->hasIsapiCredentials(),
            ]);

        return Inertia::render('Admin/Reports/Pull', [
            'devices' => $devices,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function pullStore(PullReportEventsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $startTime = Carbon::parse($data['date_from'])->startOfDay();
        $endTime = Carbon::parse($data['date_to'])->endOfDay();

        if ($startTime->diffInDays($endTime) > 31) {
            return redirect()
                ->route('admin.reports.pull')
                ->with('error', 'Maksimal oraliq 31 kun. Qisqaroq sana tanlang.');
        }

        $result = $this->eventPullService->pullDevicesInRange(
            $data['device_ids'],
            $startTime,
            $endTime,
        );

        $message = sprintf(
            'Tortib olish yakunlandi: %d yangi, %d takror, %d identifikatorsiz (o‘tkazildi), %d qurilma xato.',
            $result['imported'],
            $result['skipped_duplicate'],
            $result['skipped_invalid'],
            $result['failed'],
        );

        if ($result['truncated']) {
            $message .= ' Diqqat: juda ko‘p event — ba’zilari olinmagan bo‘lishi mumkin. Sana oralig‘ini qisqartiring.';
        }

        if ($result['imported'] === 0 && $result['failed'] > 0) {
            return redirect()
                ->route('admin.reports.pull')
                ->with('error', $message.' Qurilma ulanishi yoki login/parolni tekshiring.');
        }

        if ($result['failed'] > 0) {
            return redirect()
                ->route('admin.reports.index', [
                    'date_from' => $startTime->toDateString(),
                    'date_to' => $endTime->toDateString(),
                ])
                ->with('error', $message);
        }

        return redirect()
            ->route('admin.reports.index', [
                'date_from' => $startTime->toDateString(),
                'date_to' => $endTime->toDateString(),
            ])
            ->with('success', $message);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $filters = $this->validatedFilters($request);

        return Excel::download(
            new ReportsExport(
                $this->reportQueryService->forExport($filters),
                $this->reportQueryService->columns($filters),
            ),
            'hisobotlar.xlsx',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request): array
    {
        $filters = $request->validate($this->filterRules());

        $type = isset($filters['type']) ? AttendanceReportType::tryFrom((int) $filters['type']) : null;

        if ($type?->requiresBoundedDates()) {
            $from = isset($filters['date_from']) ? Carbon::parse($filters['date_from']) : now()->startOfDay();
            $to = isset($filters['date_to']) ? Carbon::parse($filters['date_to']) : now()->endOfDay();

            if ($from->diffInDays($to) > 93) {
                throw ValidationException::withMessages([
                    'date_to' => 'Maksimal oraliq 93 kun.',
                ]);
            }

            $filters['date_from'] ??= $from->format('Y-m-d H:i:s');
            $filters['date_to'] ??= $to->format('Y-m-d H:i:s');
        }

        return $filters;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalizeFilterPayload(array $filters): array
    {
        return [
            'employee_id' => isset($filters['employee_id']) ? (int) $filters['employee_id'] : null,
            'department_ids' => array_values(array_map('intval', $filters['department_ids'] ?? [])),
            'group_ids' => array_values(array_map('intval', $filters['group_ids'] ?? [])),
            'device_id' => isset($filters['device_id']) ? (int) $filters['device_id'] : null,
            'category_id' => isset($filters['category_id']) ? (int) $filters['category_id'] : null,
            'schedule_id' => isset($filters['schedule_id']) ? (int) $filters['schedule_id'] : null,
            'type' => isset($filters['type']) ? (int) $filters['type'] : null,
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function filterRules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => ['integer', 'exists:groups,id'],
            'device_id' => ['nullable', 'integer', 'exists:devices,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'schedule_id' => ['nullable', 'integer', 'exists:schedules,id'],
            'type' => ['nullable', 'integer', 'in:1,2,3,4,5,6,7,8'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
