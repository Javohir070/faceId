<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Report;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'device_id' => ['nullable', 'integer', 'exists:devices,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'schedule_id' => ['nullable', 'integer', 'exists:schedules,id'],
            'type' => ['nullable', 'in:entry,exit'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $query = Report::query()->latest('occurred_at');

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
            $query->where('type', $filters['type']);
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
            'department_name' => $report->department_name,
            'device_name' => $report->device_name,
            'type' => $report->type,
            'type_label' => $report->type_label,
            'occurred_at' => $report->occurred_at?->format('Y-m-d H:i:s'),
        ]);

        return Inertia::render('Admin/Reports/Index', [
            'reports' => $reports,
            'filters' => $filters,
            'employees' => Employee::query()->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'devices' => Device::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'schedules' => Schedule::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
