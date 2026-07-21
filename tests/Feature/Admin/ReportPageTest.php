<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Report;
use App\Models\Schedule;
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
}
