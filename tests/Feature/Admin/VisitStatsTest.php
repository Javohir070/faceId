<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Group;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class VisitStatsTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-19 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_user_without_live_permission_is_forbidden(): void
    {
        $user = $this->createUserWithPermissions([config('menu_permissions.home')]);

        $this->actingAs($user)
            ->get(route('admin.live.stats'))
            ->assertForbidden();
    }

    public function test_stats_cover_registration_entries_exits_inside_and_origin(): void
    {
        $department = Department::factory()->create();
        $participants = Group::factory()->create(['name' => 'Ishtirokchi', 'department_id' => $department->id]);
        $gate = Device::factory()->create(['name' => 'Kirish 2']);
        $yesterday = now()->subDay();

        $a = $this->employee($department, ['name' => 'Azizbek Karimov', 'country' => 'UZ', 'gender' => Employee::GENDER_MALE, 'group_id' => $participants->id, 'created_at' => $yesterday]);
        $b = $this->employee($department, ['country' => 'KZ', 'gender' => Employee::GENDER_FEMALE, 'created_at' => $yesterday]);
        $c = $this->employee($department, ['country' => 'UZ', 'gender' => Employee::GENDER_MALE, 'created_at' => $yesterday]);
        $this->employee($department, ['created_at' => now()->subHour()]);
        $f = $this->employee($department, ['created_at' => $yesterday]);

        $this->pass($a, 'entry', '09:00', $gate);
        $this->pass($a, 'exit', '10:00', $gate);
        $this->pass($a, 'entry', '11:30', $gate);
        $this->pass($b, 'entry', '11:10', $gate);
        $this->pass($b, 'exit', '11:40', $gate);
        $this->pass($c, 'entry', '08:00', $gate);
        $this->pass($f, 'entry', '15:00')->forceFill(['occurred_at' => '2026-10-18 15:00:00'])->save();

        $this->actingAs($this->liveUser())
            ->get(route('admin.live.stats'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Live/Stats')
                ->where('stats.registered', 5)
                ->where('stats.registered_today', 1)
                ->where('stats.entered', 3)
                ->where('stats.entered_last_hour', 2)
                ->where('stats.exited', 2)
                ->where('stats.exited_last_hour', 1)
                ->where('stats.inside', 2)
                ->where('stats.inside_male', 2)
                ->where('stats.inside_female', 0)
                ->where('stats.local', 2)
                ->where('stats.foreign', 1)
                ->where('stats.male', 2)
                ->where('stats.female', 1)
                ->where('stats.groups', [['id' => $participants->id, 'name' => 'Ishtirokchi', 'entered' => 1]])
                ->where('stats.last_arrival.employee_name', 'Azizbek Karimov')
                ->where('stats.last_arrival.country', 'UZ')
                ->where('stats.last_arrival.country_name', 'Oʻzbekiston')
                ->where('stats.last_arrival.device_name', 'Kirish 2')
                ->where('stats.last_arrival.time', '11:30:00'));
    }

    public function test_inside_uses_insertion_order_when_two_passes_share_a_timestamp(): void
    {
        $department = Department::factory()->create();
        $leaver = $this->employee($department);
        $this->pass($leaver, 'entry', '11:00');
        $this->pass($leaver, 'exit', '11:00');

        $stayer = $this->employee($department);
        $this->pass($stayer, 'exit', '11:00');
        $this->pass($stayer, 'entry', '11:00');

        $this->actingAs($this->liveUser())
            ->get(route('admin.live.stats'))
            ->assertInertia(fn (Assert $page) => $page->where('stats.inside', 1));
    }

    public function test_exit_only_person_counts_as_exited_but_not_entered(): void
    {
        $employee = $this->employee(Department::factory()->create());
        $this->pass($employee, 'exit', '11:00');

        $this->actingAs($this->liveUser())
            ->get(route('admin.live.stats'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.exited', 1)
                ->where('stats.entered', 0)
                ->where('stats.inside', 0)
                ->where('stats.last_arrival', null));
    }

    public function test_passes_without_employee_are_ignored(): void
    {
        $employee = $this->employee(Department::factory()->create());
        $this->pass($employee, 'entry', '11:00');
        Report::factory()->entry()->create(['employee_id' => null, 'occurred_at' => '2026-10-19 11:59:00']);

        $this->actingAs($this->liveUser())
            ->get(route('admin.live.stats'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.entered', 1)
                ->where('stats.inside', 1)
                ->where('stats.last_arrival.time', '11:00:00'));
    }

    public function test_department_user_sees_only_own_department(): void
    {
        $own = Department::factory()->create();
        $other = Department::factory()->create();
        $mine = $this->employee($own, ['name' => 'Oʻz xodim']);
        $foreigner = $this->employee($other, ['name' => 'Boshqa xodim']);
        $this->pass($mine, 'entry', '10:00');
        $this->pass($foreigner, 'entry', '11:00');

        $user = $this->createDepartmentUserWithPermissions($own->id, [config('menu_permissions.resources')['live']]);

        $this->actingAs($user)
            ->get(route('admin.live.stats'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.registered', 1)
                ->where('stats.entered', 1)
                ->where('stats.inside', 1)
                ->where('stats.last_arrival.employee_name', 'Oʻz xodim'));
    }

    public function test_department_user_without_department_sees_zeroes(): void
    {
        $department = Department::factory()->create();
        $employee = $this->employee($department);
        $this->pass($employee, 'entry', '11:00');
        Group::factory()->create(['department_id' => $department->id]);

        $user = $this->createDepartmentUserWithPermissions($department->id, [config('menu_permissions.resources')['live']]);
        $user->forceFill(['department_id' => null])->save();

        $this->actingAs($user->fresh())
            ->get(route('admin.live.stats'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.registered', 0)
                ->where('stats.entered', 0)
                ->where('stats.inside', 0)
                ->where('stats.groups', [])
                ->where('stats.last_arrival', null));
    }

    private function liveUser(): User
    {
        return $this->createUserWithPermissions([config('menu_permissions.resources')['live']]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function employee(Department $department, array $attributes = []): Employee
    {
        return Employee::factory()->create(['department_id' => $department->id] + $attributes);
    }

    /**
     * ReportFactory har chaqiriqda yangi xodim yaratadi — `registered` buzilmasligi uchun to‘g‘ridan-to‘g‘ri yoziladi.
     */
    private function pass(Employee $employee, string $type, string $time, ?Device $device = null): Report
    {
        return Report::query()->create([
            'employee_id' => $employee->id,
            'department_id' => $employee->department_id,
            'device_id' => $device?->id,
            'employee_name' => $employee->name,
            'type' => $type === 'exit' ? Report::TYPE_EXIT : Report::TYPE_ENTRY,
            'occurred_at' => '2026-10-19 '.$time.':00',
        ]);
    }
}
