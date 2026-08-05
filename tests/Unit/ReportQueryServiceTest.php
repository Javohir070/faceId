<?php

namespace Tests\Unit;

use App\Enums\AttendanceReportType;
use App\Enums\NoteType;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Group;
use App\Models\Note;
use App\Models\Report;
use App\Models\Schedule;
use App\Services\ReportQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function raw_logs_filter_by_entry_type_and_expose_columns(): void
    {
        $department = Department::factory()->create();
        $schedule = Schedule::factory()->create(['enter_time' => '09:00', 'exit_time' => '18:00']);
        $group = Group::factory()->create(['department_id' => $department->id, 'name' => 'G1']);
        $employee = Employee::factory()->create([
            'department_id' => $department->id,
            'schedule_id' => $schedule->id,
            'group_id' => $group->id,
            'position' => 'Dev',
            'name' => 'Ali',
        ]);
        $device = Device::factory()->create(['name' => 'Door 1']);

        Report::factory()->forEmployee($employee)->forDevice($device)->entry()->create([
            'occurred_at' => '2026-08-04 09:05:00',
        ]);
        Report::factory()->forEmployee($employee)->forDevice($device)->exit()->create([
            'occurred_at' => '2026-08-04 18:00:00',
        ]);

        $service = app(ReportQueryService::class);
        $filters = ['type' => AttendanceReportType::Entry->value];

        $columns = $service->columns($filters);
        $this->assertSame(
            ['employee_name', 'group_name', 'position', 'device_name', 'type_label', 'occurred_at'],
            array_column($columns, 'key'),
        );

        $page = $service->paginate($filters, 25);
        $this->assertCount(1, $page->items());
        $this->assertSame('Ali', $page->items()[0]['employee_name']);
        $this->assertSame('Kirish', $page->items()[0]['type_label']);
        $this->assertSame('G1', $page->items()[0]['group_name']);
    }

    #[Test]
    public function late_report_includes_only_late_first_entries_with_note(): void
    {
        $department = Department::factory()->create();
        $schedule = Schedule::factory()->create(['enter_time' => '09:00', 'exit_time' => '18:00']);
        $employee = Employee::factory()->create([
            'department_id' => $department->id,
            'schedule_id' => $schedule->id,
            'name' => 'Late Ali',
            'position' => 'Dev',
        ]);
        $device = Device::factory()->create();

        Report::factory()->forEmployee($employee)->forDevice($device)->entry()->create([
            'occurred_at' => '2026-08-04 09:20:00',
        ]);

        $onTime = Employee::factory()->create([
            'department_id' => $department->id,
            'schedule_id' => $schedule->id,
            'name' => 'On Time',
        ]);
        Report::factory()->forEmployee($onTime)->forDevice($device)->entry()->create([
            'occurred_at' => '2026-08-04 08:55:00',
        ]);

        Note::factory()->create([
            'employee_id' => $employee->id,
            'type' => NoteType::CameLate,
            'dt' => '2026-08-04',
            'note' => 'Tirbandlik',
        ]);

        $page = app(ReportQueryService::class)->paginate([
            'type' => AttendanceReportType::Late->value,
            'date_from' => '2026-08-04 00:00:00',
            'date_to' => '2026-08-04 23:59:59',
        ], 25);

        $this->assertCount(1, $page->items());
        $this->assertSame('Late Ali', $page->items()[0]['employee_name']);
        $this->assertSame('00:20:00', $page->items()[0]['late']);
        $this->assertSame('Tirbandlik', $page->items()[0]['reason']);
    }

    #[Test]
    public function left_early_report_detects_early_exit(): void
    {
        $department = Department::factory()->create();
        $schedule = Schedule::factory()->create(['enter_time' => '09:00', 'exit_time' => '18:00']);
        $employee = Employee::factory()->create([
            'department_id' => $department->id,
            'schedule_id' => $schedule->id,
            'name' => 'Early Bob',
        ]);
        $device = Device::factory()->create();
        Report::factory()->forEmployee($employee)->forDevice($device)->exit()->create([
            'occurred_at' => '2026-08-04 17:00:00',
        ]);

        $page = app(ReportQueryService::class)->paginate([
            'type' => AttendanceReportType::LeftEarly->value,
            'date_from' => '2026-08-04 00:00:00',
            'date_to' => '2026-08-04 23:59:59',
        ], 25);

        $this->assertCount(1, $page->items());
        $this->assertSame('01:00:00', $page->items()[0]['early']);
    }

    #[Test]
    public function present_lists_employees_with_punch_on_day(): void
    {
        $department = Department::factory()->create();
        $schedule = Schedule::factory()->create();
        $device = Device::factory()->create();
        $present = Employee::factory()->create([
            'department_id' => $department->id,
            'schedule_id' => $schedule->id,
            'name' => 'Present Emp',
        ]);
        Employee::factory()->create([
            'department_id' => $department->id,
            'schedule_id' => $schedule->id,
            'name' => 'Missing Emp',
        ]);
        Report::factory()->forEmployee($present)->forDevice($device)->entry()->create([
            'occurred_at' => '2026-08-04 10:00:00',
        ]);

        $page = app(ReportQueryService::class)->paginate([
            'type' => AttendanceReportType::Present->value,
            'date_from' => '2026-08-04 00:00:00',
            'date_to' => '2026-08-04 23:59:59',
        ], 25);

        $this->assertCount(1, $page->items());
        $this->assertSame('Present Emp', $page->items()[0]['employee_name']);
        $this->assertSame('2026-08-04', $page->items()[0]['date']);
    }

    #[Test]
    public function absent_lists_employees_without_punch(): void
    {
        $department = Department::factory()->create();
        $schedule = Schedule::factory()->create();
        $device = Device::factory()->create();
        $present = Employee::factory()->create([
            'department_id' => $department->id,
            'schedule_id' => $schedule->id,
            'name' => 'Came',
        ]);
        $absent = Employee::factory()->create([
            'department_id' => $department->id,
            'schedule_id' => $schedule->id,
            'name' => 'Did Not Come',
        ]);
        Report::factory()->forEmployee($present)->forDevice($device)->entry()->create([
            'occurred_at' => '2026-08-04 10:00:00',
        ]);
        Note::factory()->create([
            'employee_id' => $absent->id,
            'type' => NoteType::Absent,
            'dt' => '2026-08-04',
            'note' => 'Kasallik',
        ]);

        $page = app(ReportQueryService::class)->paginate([
            'type' => AttendanceReportType::Absent->value,
            'department_ids' => [$department->id],
            'date_from' => '2026-08-04 00:00:00',
            'date_to' => '2026-08-04 23:59:59',
        ], 25);

        $this->assertCount(1, $page->items());
        $this->assertSame('Did Not Come', $page->items()[0]['employee_name']);
        $this->assertSame('Kasallik', $page->items()[0]['reason']);
    }

    #[Test]
    public function individual_shows_first_in_last_out_per_day(): void
    {
        $department = Department::factory()->create();
        $schedule = Schedule::factory()->create(['enter_time' => '09:00', 'exit_time' => '18:00']);
        $employee = Employee::factory()->create([
            'department_id' => $department->id,
            'schedule_id' => $schedule->id,
            'name' => 'Ind Emp',
        ]);
        $device = Device::factory()->create();
        Report::factory()->forEmployee($employee)->forDevice($device)->entry()->create([
            'occurred_at' => '2026-08-04 09:00:00',
        ]);
        Report::factory()->forEmployee($employee)->forDevice($device)->exit()->create([
            'occurred_at' => '2026-08-04 18:00:00',
        ]);

        $page = app(ReportQueryService::class)->paginate([
            'type' => AttendanceReportType::Individual->value,
            'date_from' => '2026-08-04 00:00:00',
            'date_to' => '2026-08-04 23:59:59',
        ], 25);

        $this->assertCount(1, $page->items());
        $this->assertSame('2026-08-04 09:00:00', $page->items()[0]['check_in']);
        $this->assertSame('2026-08-04 18:00:00', $page->items()[0]['check_out']);
    }

    #[Test]
    public function work_time_pivots_hours_per_day_with_total(): void
    {
        $department = Department::factory()->create();
        $schedule = Schedule::factory()->create(['enter_time' => '09:00', 'exit_time' => '18:00']);
        $employee = Employee::factory()->create([
            'department_id' => $department->id,
            'schedule_id' => $schedule->id,
            'name' => 'Work Emp',
        ]);
        $device = Device::factory()->create();

        Report::factory()->forEmployee($employee)->forDevice($device)->entry()->create([
            'occurred_at' => '2026-08-04 09:00:00',
        ]);
        Report::factory()->forEmployee($employee)->forDevice($device)->exit()->create([
            'occurred_at' => '2026-08-04 17:00:00',
        ]);
        Report::factory()->forEmployee($employee)->forDevice($device)->entry()->create([
            'occurred_at' => '2026-08-05 09:00:00',
        ]);
        Report::factory()->forEmployee($employee)->forDevice($device)->exit()->create([
            'occurred_at' => '2026-08-05 13:00:00',
        ]);

        $service = app(ReportQueryService::class);
        $filters = [
            'type' => AttendanceReportType::WorkTime->value,
            'date_from' => '2026-08-04 00:00:00',
            'date_to' => '2026-08-05 23:59:59',
        ];
        $keys = array_column($service->columns($filters), 'key');
        $this->assertContains('2026-08-04', $keys);
        $this->assertContains('2026-08-05', $keys);
        $this->assertContains('total', $keys);

        $page = $service->paginate($filters, 25);
        $this->assertCount(1, $page->items());
        $this->assertSame('08:00:00', $page->items()[0]['2026-08-04']);
        $this->assertSame('04:00:00', $page->items()[0]['2026-08-05']);
        $this->assertSame('12:00:00', $page->items()[0]['total']);
    }
}
