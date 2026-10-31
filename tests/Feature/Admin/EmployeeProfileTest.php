<?php

namespace Tests\Feature\Admin;

use App\Enums\NoteType;
use App\Jobs\Hikvision\RemoveHikvisionUsersJob;
use App\Models\Category;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Note;
use App\Models\Report;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class EmployeeProfileTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function admin(): User
    {
        return $this->createUserWithPermissions([
            config('menu_permissions.resources')['employees'],
        ]);
    }

    private function device(User $owner, string $name): Device
    {
        return Device::factory()->create([
            'user_id' => $owner->id,
            'category_id' => Category::factory()->create()->id,
            'name' => $name,
        ]);
    }

    private function pass(Employee $employee, string $at, int $type, ?Device $device = null): void
    {
        Report::query()->create([
            'employee_id' => $employee->id,
            'department_id' => $employee->department_id,
            'device_id' => $device?->id,
            'employee_name' => $employee->name,
            'type' => $type,
            'occurred_at' => $at,
        ]);
    }

    private function officeEmployee(): Employee
    {
        return Employee::factory()->create([
            'schedule_id' => Schedule::factory()->create(['enter_time' => '09:00:00', 'exit_time' => '18:00:00'])->id,
            'valid_from_dt' => '2026-01-01 00:00:00',
            'valid_to_dt' => '2027-01-01 00:00:00',
        ]);
    }

    public function test_bulk_destroy_deletes_selected_employees_and_queues_device_removal(): void
    {
        Queue::fake();
        $admin = $this->admin();
        [$synced, $plain, $kept] = Employee::factory()->count(3)->create()->all();
        $synced->devices()->attach($this->device($admin, 'Kirish')->id, ['synced_at' => now()]);

        $this->actingAs($admin)
            ->from(route('admin.employees.index'))
            ->post(route('admin.employees.bulk-destroy'), ['employee_ids' => [$synced->id, $plain->id]])
            ->assertRedirect(route('admin.employees.index'))
            ->assertSessionHas('success', '2 ta xodim o‘chirildi. Qurilmalardan olib tashlash navbatga qo‘yildi.');

        $this->assertDatabaseMissing('employees', ['id' => $synced->id]);
        $this->assertDatabaseMissing('employees', ['id' => $plain->id]);
        $this->assertDatabaseHas('employees', ['id' => $kept->id]);
        Queue::assertPushed(RemoveHikvisionUsersJob::class, 1);
    }

    public function test_bulk_destroy_rejects_employees_from_another_department(): void
    {
        Queue::fake();
        $own = Department::factory()->create();
        $other = Department::factory()->create();
        $user = $this->createDepartmentUserWithPermissions($own->id);
        $mine = Employee::factory()->create(['department_id' => $own->id]);
        $foreign = Employee::factory()->create(['department_id' => $other->id]);

        $this->actingAs($user)
            ->post(route('admin.employees.bulk-destroy'), ['employee_ids' => [$mine->id, $foreign->id]])
            ->assertSessionHasErrors('employee_ids');

        $this->assertDatabaseHas('employees', ['id' => $mine->id]);
        $this->assertDatabaseHas('employees', ['id' => $foreign->id]);
    }

    public function test_show_month_calendar_has_statuses_exit_counts_and_notes(): void
    {
        Carbon::setTestNow('2026-10-21 20:00:00');
        $employee = $this->officeEmployee();

        // Dushanba: o‘z vaqtida, tushlikka chiqib qaytgan.
        $this->pass($employee, '2026-10-19 08:55:00', Report::TYPE_ENTRY);
        $this->pass($employee, '2026-10-19 12:00:00', Report::TYPE_EXIT);
        $this->pass($employee, '2026-10-19 12:50:00', Report::TYPE_ENTRY);
        $this->pass($employee, '2026-10-19 18:05:00', Report::TYPE_EXIT);
        // Seshanba: kech qolgan.
        $this->pass($employee, '2026-10-20 09:30:00', Report::TYPE_ENTRY);
        $this->pass($employee, '2026-10-20 18:00:00', Report::TYPE_EXIT);
        // Chorshanba: ruxsat.
        Note::factory()->create(['employee_id' => $employee->id, 'type' => NoteType::Absent, 'dt' => '2026-10-21']);

        $this->actingAs($this->admin())
            ->get(route('admin.employees.show', ['employee' => $employee, 'month' => '2026-10']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Employees/Show')
                ->where('month.month', '2026-10')
                ->where('month.prev', '2026-09')
                ->where('month.next', '2026-11')
                ->has('month.days', 31)
                ->where('month.days.18.date', '2026-10-19')
                ->where('month.days.18.status', 'on_time')
                ->where('month.days.18.in', '08:55')
                ->where('month.days.18.out', '18:05')
                ->where('month.days.18.entries', 2)
                ->where('month.days.18.exits', 2)
                ->where('month.days.19.status', 'late')
                ->where('month.days.20.status', 'excused')
                ->where('month.days.20.notes.0', 'Kelmadi')
                ->where('month.days.17.is_weekend', true)
                ->where('month.days.15.status', 'absent')
                ->where('month.days.22.status', 'future')
                ->where('month.stats.on_time', 1)
                ->where('month.stats.late', 1)
                ->where('month.stats.excused', 1)
                ->where('month.stats.entries', 3)
                ->where('month.stats.exits', 3));
    }

    public function test_show_passes_are_filtered_by_dates_type_and_device(): void
    {
        Carbon::setTestNow('2026-10-21 20:00:00');
        $admin = $this->admin();
        $employee = $this->officeEmployee();
        $gate = $this->device($admin, 'Asosiy kirish');
        $back = $this->device($admin, 'Orqa eshik');

        $this->pass($employee, '2026-09-30 09:00:00', Report::TYPE_ENTRY, $gate);
        $this->pass($employee, '2026-10-19 08:55:00', Report::TYPE_ENTRY, $gate);
        $this->pass($employee, '2026-10-19 12:00:00', Report::TYPE_EXIT, $back);
        $this->pass($employee, '2026-10-20 18:00:00', Report::TYPE_EXIT, $gate);

        // Standart: tanlangan oy oralig‘i, eng yangisi birinchi.
        $this->actingAs($admin)
            ->get(route('admin.employees.show', ['employee' => $employee, 'month' => '2026-10']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.from', '2026-10-01')
                ->where('filters.to', '2026-10-31')
                ->has('passes.data', 3)
                ->where('passes.data.0.date', '2026-10-20')
                ->where('passes.data.0.time', '18:00:00')
                ->where('passes.data.0.type', 'exit')
                ->where('passes_summary.entries', 1)
                ->where('passes_summary.exits', 2)
                ->has('pass_devices', 2));

        $this->actingAs($admin)
            ->get(route('admin.employees.show', [
                'employee' => $employee,
                'from' => '2026-10-19',
                'to' => '2026-10-19',
                'type' => 'exit',
                'device_id' => $back->id,
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('passes.data', 1)
                ->where('passes.data.0.time', '12:00:00')
                ->where('passes.data.0.place', fn ($place) => str_contains($place, 'Orqa eshik')));
    }

    public function test_show_loads_day_detail_for_selected_day(): void
    {
        Carbon::setTestNow('2026-10-21 20:00:00');
        $employee = $this->officeEmployee();
        $this->pass($employee, '2026-10-19 08:55:00', Report::TYPE_ENTRY);
        $this->pass($employee, '2026-10-19 18:05:00', Report::TYPE_EXIT);

        $this->actingAs($this->admin())
            ->get(route('admin.employees.show', ['employee' => $employee, 'day' => '2026-10-19']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('day.date', '2026-10-19')
                ->where('day.status', 'on_time')
                ->has('day.punches', 2));
    }

    public function test_note_form_can_be_prefilled_with_date(): void
    {
        $employee = Employee::factory()->create();
        $admin = $this->createUserWithPermissions([config('menu_permissions.resources')['notes']]);

        $this->actingAs($admin)
            ->get(route('admin.notes.create', ['employee_id' => $employee->id, 'dt' => '2026-10-21']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('prefillEmployeeId', $employee->id)
                ->where('prefillDate', '2026-10-21'));
    }
}
