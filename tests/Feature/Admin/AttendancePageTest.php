<?php

namespace Tests\Feature\Admin;

use App\Enums\NoteType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Note;
use App\Models\Report;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class AttendancePageTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-23 12:00:00'));
    }

    public function test_page_requires_attendance_permission(): void
    {
        $this->assertTrue(Permission::query()->where('name', 'Davomat')->exists());

        $user = $this->createUserWithPermissions([config('menu_permissions.resources')['reports']]);

        $this->actingAs($user)->get(route('admin.attendance.index'))->assertForbidden();
    }

    public function test_page_renders_grid_and_stats(): void
    {
        $employee = $this->employee();
        $this->punch($employee, '2026-10-22 08:00', 'in');
        $this->punch($employee, '2026-10-22 18:00', 'out');

        $this->actingAs($this->admin())
            ->get(route('admin.attendance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Attendance/Index')
                ->where('period.label', '12.10 – 23.10.2026')
                ->where('period.type', '2weeks')
                ->has('days', 10)
                ->has('rows.data', 1)
                ->has('rows.data.0.cells', 10)
                ->where('rows.data.0.name', $employee->name)
                ->where('stats.on_time', 1)
                ->where('department_counts.'.$employee->department_id, 1)
                ->where('day', null));
    }

    public function test_filters_by_department_and_search(): void
    {
        $it = Department::factory()->create(['name' => 'IT']);
        $hr = Department::factory()->create(['name' => 'Kadrlar']);
        $this->employee(['name' => 'Ali Valiyev', 'department_id' => $it->id]);
        $this->employee(['name' => 'Olim Karimov', 'department_id' => $hr->id]);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.attendance.index', ['department_id' => $hr->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 1)
                ->where('rows.data.0.name', 'Olim Karimov'));

        $this->actingAs($admin)
            ->get(route('admin.attendance.index', ['search' => 'Vali']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 1)
                ->where('rows.data.0.name', 'Ali Valiyev'));
    }

    public function test_rows_are_paginated_by_fifty(): void
    {
        $department = Department::factory()->create();
        $schedule = $this->schedule();
        Employee::factory()->count(51)->create([
            'department_id' => $department->id,
            'schedule_id' => $schedule->id,
            'valid_from_dt' => '2026-01-01 00:00:00',
            'valid_to_dt' => '2027-12-31 00:00:00',
        ]);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.attendance.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 50)
                ->where('rows.total', 51)
                ->where('stats.absent', 51 * 9));

        $this->actingAs($admin)
            ->get(route('admin.attendance.index', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->has('rows.data', 1));
    }

    public function test_department_user_sees_only_own_department(): void
    {
        $own = Department::factory()->create();
        $other = Department::factory()->create();
        $this->employee(['name' => 'O‘zimniki', 'department_id' => $own->id]);
        $this->employee(['name' => 'Begona', 'department_id' => $other->id]);

        $user = $this->createDepartmentUserWithPermissions($own->id, [config('menu_permissions.resources')['attendance']]);

        $this->actingAs($user)
            ->get(route('admin.attendance.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 1)
                ->where('rows.data.0.name', 'O‘zimniki'));
    }

    public function test_day_detail_is_returned_for_requested_cell(): void
    {
        $employee = $this->employee();
        $this->punch($employee, '2026-10-22 08:00', 'in');

        $this->actingAs($this->admin())
            ->get(route('admin.attendance.index', ['day_employee_id' => $employee->id, 'day_date' => '2026-10-22']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('day.in', '08:00')
                ->where('day.employee.id', $employee->id));
    }

    public function test_day_detail_respects_department_scope(): void
    {
        $own = Department::factory()->create();
        $other = Department::factory()->create();
        $foreign = $this->employee(['department_id' => $other->id]);
        $this->employee(['department_id' => $own->id]);

        $user = $this->createDepartmentUserWithPermissions($own->id, [config('menu_permissions.resources')['attendance']]);

        $this->actingAs($user)
            ->get(route('admin.attendance.index', ['day_employee_id' => $foreign->id, 'day_date' => '2026-10-22']))
            ->assertInertia(fn (Assert $page) => $page->where('day', null));
    }

    public function test_notes_tab_lists_period_notes(): void
    {
        $employee = $this->employee();
        Note::factory()->create(['employee_id' => $employee->id, 'dt' => '2026-10-16', 'type' => NoteType::LeftEarly]);
        Note::factory()->create(['employee_id' => $employee->id, 'dt' => '2026-09-01', 'type' => NoteType::Absent]);

        $this->actingAs($this->admin())
            ->get(route('admin.attendance.index', ['tab' => 'notes']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('notes.data', 1)
                ->where('notes.data.0.dt', '2026-10-16')
                ->where('notes.data.0.type_label', 'Erta ketdi'));
    }

    public function test_store_note_redirects_back_with_success(): void
    {
        $employee = $this->employee();
        $from = route('admin.attendance.index', ['date' => '2026-10-23']);

        $this->actingAs($this->admin())
            ->from($from)
            ->post(route('admin.attendance.notes.store'), [
                'employee_id' => $employee->id,
                'type' => NoteType::CameLate->value,
                'dt' => '2026-10-21',
                'note' => 'Tirbandlik',
            ])
            ->assertRedirect($from)
            ->assertSessionHas('success');

        $this->assertTrue(Note::query()->where('employee_id', $employee->id)->whereDate('dt', '2026-10-21')->exists());
    }

    public function test_store_note_requires_notes_permission(): void
    {
        $employee = $this->employee();
        $user = $this->createUserWithPermissions([config('menu_permissions.resources')['attendance']]);

        $this->actingAs($user)
            ->post(route('admin.attendance.notes.store'), [
                'employee_id' => $employee->id,
                'type' => NoteType::CameLate->value,
                'dt' => '2026-10-21',
                'note' => 'Tirbandlik',
            ])
            ->assertForbidden();
    }

    private function admin(): User
    {
        return $this->createUserWithPermissions([
            config('menu_permissions.resources')['attendance'],
            config('menu_permissions.resources')['notes'],
        ]);
    }

    private function schedule(): Schedule
    {
        return Schedule::factory()->create(['enter_time' => '09:00', 'exit_time' => '18:00']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function employee(array $attributes = []): Employee
    {
        return Employee::factory()->create(array_merge([
            'schedule_id' => $this->schedule()->id,
            'valid_from_dt' => '2026-01-01 00:00:00',
            'valid_to_dt' => '2027-12-31 00:00:00',
        ], $attributes));
    }

    /**
     * ReportFactory har yozuvga yangi xodim yaratadi — jadvalda ortiqcha qator chiqmasligi uchun to‘g‘ridan-to‘g‘ri yoziladi.
     */
    private function punch(Employee $employee, string $at, string $direction): void
    {
        Report::query()->create([
            'employee_id' => $employee->id,
            'department_id' => $employee->department_id,
            'employee_name' => $employee->name,
            'type' => $direction === 'in' ? Report::TYPE_ENTRY : Report::TYPE_EXIT,
            'occurred_at' => $at,
        ]);
    }
}
