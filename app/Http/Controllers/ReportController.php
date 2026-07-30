<?php

namespace App\Http\Controllers;

use App\Http\Requests\PullReportEventsRequest;
use App\Models\Category;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Report;
use App\Models\Schedule;
use App\Services\Hikvision\HikvisionEventPullService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(
        private readonly HikvisionEventPullService $eventPullService,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'device_id' => ['nullable', 'integer', 'exists:devices,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'schedule_id' => ['nullable', 'integer', 'exists:schedules,id'],
            'type' => ['nullable', 'integer', 'in:1,2'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $query = Report::query()
            ->with(['department:id,name', 'device:id,name'])
            ->latest('occurred_at');

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
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

        if (! empty($filters['type'])) {
            $query->where('type', (int) $filters['type']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('occurred_at', '>=', $filters['date_from'].' 00:00:00');
        }

        if (! empty($filters['date_to'])) {
            $query->where('occurred_at', '<=', $filters['date_to'].' 23:59:59');
        }

        $reports = $query
            ->paginate(25)
            ->withQueryString();

        $reports->getCollection()->transform(fn (Report $report): array => [
            'id' => $report->id,
            'employee_name' => $report->employee_name,
            'department_name' => $report->department?->name,
            'device_name' => $report->device?->name,
            'type' => $report->type,
            'type_label' => $report->type_label,
            'occurred_at' => $report->occurred_at?->format('Y-m-d H:i:s'),
        ]);

        return Inertia::render('Admin/Reports/Index', [
            'reports' => $reports,
            'filters' => [
                ...$filters,
                'type' => isset($filters['type']) ? (int) $filters['type'] : null,
            ],
            'employees' => Employee::query()->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
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
            'Tortib olish yakunlandi: %d yangi, %d takror (o‘tkazib yuborildi), %d qurilma xato.',
            $result['imported'],
            $result['skipped'],
            $result['failed'],
        );

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
}
