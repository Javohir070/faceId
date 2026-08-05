<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Note;
use App\Models\Report;
use App\Models\Schedule;
use App\Enums\NoteType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class ReportPageTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    public function test_authorized_user_can_view_reports_page(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['reports'],
        ]);

        $department = Department::factory()->create(['name' => 'IT']);
        $schedule = Schedule::factory()->create();
        $employee = Employee::factory()->create([
            'department_id' => $department->id,
            'schedule_id' => $schedule->id,
        ]);
        $device = Device::factory()->create(['name' => 'Kirish 1']);

        Report::factory()->forEmployee($employee)->forDevice($device)->entry()->create([
            'occurred_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports/Index')
                ->has('columns')
                ->has('reports.data', 1)
                ->where('reports.data.0.employee_name', $employee->name)
                ->where('reports.data.0.type_label', 'Kirish'));
    }

    public function test_reports_page_can_filter_by_type(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['reports'],
        ]);

        Report::factory()->entry()->create(['employee_name' => 'Kirish xodim']);
        Report::factory()->exit()->create(['employee_name' => 'Chiqish xodim']);

        $this->actingAs($admin)
            ->get(route('admin.reports.index', ['type' => Report::TYPE_EXIT]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('reports.data', 1)
                ->where('reports.data.0.type', Report::TYPE_EXIT));
    }

    public function test_unauthorized_user_cannot_access_reports_page(): void
    {
        $user = $this->createUserWithPermissions([
            config('menu_permissions.home'),
        ]);

        $this->actingAs($user)
            ->get(route('admin.reports.index'))
            ->assertForbidden();
    }

    public function test_present_absent_reject_range_over_93_days(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['reports'],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'type' => 5,
                'date_from' => '2026-01-01 00:00:00',
                'date_to' => '2026-05-01 23:59:59',
            ]))
            ->assertSessionHasErrors(['date_to']);
    }

    public function test_late_type_returns_late_rows(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['reports'],
        ]);
        [$employee, $device] = $this->seedScheduledEmployee('09:00', '18:00');
        Report::factory()->forEmployee($employee)->forDevice($device)->entry()->create([
            'occurred_at' => '2026-08-04 09:30:00',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'type' => 3,
                'date_from' => '2026-08-04 00:00:00',
                'date_to' => '2026-08-04 23:59:59',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('columns.0.key', 'employee_name')
                ->has('reports.data', 1)
                ->where('reports.data.0.late', '00:30:00'));
    }

    public function test_left_early_type_returns_rows(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['reports'],
        ]);
        [$employee, $device] = $this->seedScheduledEmployee('09:00', '18:00');
        Report::factory()->forEmployee($employee)->forDevice($device)->exit()->create([
            'occurred_at' => '2026-08-04 17:00:00',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'type' => 4,
                'date_from' => '2026-08-04 00:00:00',
                'date_to' => '2026-08-04 23:59:59',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('reports.data', 1)
                ->where('reports.data.0.early', '01:00:00'));
    }

    public function test_present_type_returns_rows(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['reports'],
        ]);
        [$employee, $device, $department] = $this->seedScheduledEmployee('09:00', '18:00');
        Report::factory()->forEmployee($employee)->forDevice($device)->entry()->create([
            'occurred_at' => '2026-08-04 10:00:00',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'type' => 5,
                'department_ids' => [$department->id],
                'date_from' => '2026-08-04 00:00:00',
                'date_to' => '2026-08-04 23:59:59',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('reports.data', 1)
                ->where('reports.data.0.date', '2026-08-04'));
    }

    public function test_absent_type_returns_rows(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['reports'],
        ]);
        [$employee, $device, $department] = $this->seedScheduledEmployee('09:00', '18:00');
        Note::factory()->create([
            'employee_id' => $employee->id,
            'type' => NoteType::Absent,
            'dt' => '2026-08-04',
            'note' => 'Sababsiz',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'type' => 6,
                'department_ids' => [$department->id],
                'date_from' => '2026-08-04 00:00:00',
                'date_to' => '2026-08-04 23:59:59',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('reports.data', 1)
                ->where('reports.data.0.reason', 'Sababsiz'));
    }

    public function test_work_time_type_returns_rows(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['reports'],
        ]);
        [$employee, $device] = $this->seedScheduledEmployee('09:00', '18:00');
        Report::factory()->forEmployee($employee)->forDevice($device)->entry()->create([
            'occurred_at' => '2026-08-04 09:00:00',
        ]);
        Report::factory()->forEmployee($employee)->forDevice($device)->exit()->create([
            'occurred_at' => '2026-08-04 17:00:00',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'type' => 7,
                'date_from' => '2026-08-04 00:00:00',
                'date_to' => '2026-08-04 23:59:59',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('reports.data', 1)
                ->where('reports.data.0.total', '08:00:00'));
    }

    public function test_individual_type_returns_rows(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['reports'],
        ]);
        [$employee, $device] = $this->seedScheduledEmployee('09:00', '18:00');
        Report::factory()->forEmployee($employee)->forDevice($device)->entry()->create([
            'occurred_at' => '2026-08-04 09:05:00',
        ]);
        Report::factory()->forEmployee($employee)->forDevice($device)->exit()->create([
            'occurred_at' => '2026-08-04 18:10:00',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'type' => 8,
                'date_from' => '2026-08-04 00:00:00',
                'date_to' => '2026-08-04 23:59:59',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('reports.data', 1)
                ->where('reports.data.0.check_in', '2026-08-04 09:05:00')
                ->where('reports.data.0.check_out', '2026-08-04 18:10:00'));
    }

    /**
     * @return array{0: Employee, 1: Device, 2: Department}
     */
    private function seedScheduledEmployee(string $enter, string $exit): array
    {
        $department = Department::factory()->create();
        $schedule = Schedule::factory()->create([
            'enter_time' => $enter,
            'exit_time' => $exit,
        ]);
        $employee = Employee::factory()->create([
            'department_id' => $department->id,
            'schedule_id' => $schedule->id,
        ]);
        $device = Device::factory()->create();

        return [$employee, $device, $department];
    }
}
