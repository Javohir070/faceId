<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Group;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class EntrantListTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    private Department $department;

    private Group $participants;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 29)->setTime(15, 0));

        $this->department = Department::factory()->create(['name' => 'Innoweek 2026']);
        $this->participants = Group::factory()->create(['name' => 'Ishtirokchi', 'department_id' => $this->department->id]);
        $this->device = Device::factory()->create();
    }

    public function test_page_requires_sq_permission(): void
    {
        $viewer = $this->createUserWithPermissions([config('menu_permissions.resources')['live']]);

        $this->actingAs($viewer)->get(route('admin.live.entrants.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.live.entrants.export'))->assertForbidden();
    }

    public function test_lists_visitors_and_staff_who_entered_with_first_entry_per_day(): void
    {
        $guest = $this->visitor('Aliyev Vali', '1001');
        $staff = Employee::factory()->create(['name' => 'Bakirov Sardor', 'card' => '2002', 'department_id' => $this->department->id]);
        $exitOnly = $this->visitor('Chiqqan Faqat', '3003');
        $outside = $this->visitor('Oldin Kelgan', '4004');

        $this->pass($guest, '2026-10-27 10:15:00');
        $this->pass($guest, '2026-10-27 09:05:00');
        $this->pass($guest, '2026-10-29 11:00:00');
        $this->pass($staff, '2026-10-28 08:30:00');
        $this->pass($exitOnly, '2026-10-28 12:00:00', Report::TYPE_EXIT);
        $this->pass($outside, '2026-10-26 12:00:00');

        $this->actingAs($this->admin())
            ->get(route('admin.live.entrants.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Live/Entrants')
                ->where('filters.from', '2026-10-27')
                ->where('filters.to', '2026-10-29')
                ->where('days', ['2026-10-27', '2026-10-28', '2026-10-29'])
                ->where('dailyTotals', ['2026-10-27' => 1, '2026-10-28' => 1, '2026-10-29' => 1])
                ->where('entrants.total', 2)
                ->where('entrants.data.0.name', 'Aliyev Vali')
                ->where('entrants.data.0.group_name', 'Ishtirokchi')
                ->where('entrants.data.0.position', 'Talaba')
                ->where('entrants.data.0.days', ['2026-10-27' => '09:05', '2026-10-28' => null, '2026-10-29' => '11:00'])
                ->where('entrants.data.0.days_count', 2)
                ->where('entrants.data.0.passes', 3)
                ->where('entrants.data.1.name', 'Bakirov Sardor')
                ->where('entrants.data.1.days.2026-10-28', '08:30'));
    }

    public function test_filters_by_dates_search_and_group(): void
    {
        $guest = $this->visitor('Aliyev Vali', '1001');
        $other = $this->visitor('Karimova Nodira', '5005', null);

        $this->pass($guest, '2026-10-27 10:00:00');
        $this->pass($other, '2026-10-28 10:00:00');

        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.live.entrants.index', ['from' => '2026-10-28', 'to' => '2026-10-28']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('days', ['2026-10-28'])
                ->where('entrants.total', 1)
                ->where('entrants.data.0.name', 'Karimova Nodira'));

        $this->actingAs($admin)
            ->get(route('admin.live.entrants.index', ['search' => '1001']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('entrants.total', 1)
                ->where('entrants.data.0.name', 'Aliyev Vali'));

        $this->actingAs($admin)
            ->get(route('admin.live.entrants.index', ['group_id' => $this->participants->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('entrants.total', 1)
                ->where('dailyTotals', ['2026-10-27' => 1, '2026-10-28' => 0, '2026-10-29' => 0]));
    }

    public function test_long_range_is_cut_to_max_days(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.live.entrants.index', ['from' => '2026-01-01', 'to' => '2026-10-29']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.from', '2026-09-29')
                ->has('days', 31));
    }

    public function test_export_downloads_excel(): void
    {
        $this->pass($this->visitor('Aliyev Vali', '1001'), '2026-10-27 10:00:00');

        $this->actingAs($this->admin())
            ->get(route('admin.live.entrants.export'))
            ->assertOk()
            ->assertDownload('kirganlar_2026-10-27_2026-10-29.xlsx');
    }

    private function visitor(string $name, string $card, ?int $groupId = -1): Employee
    {
        return Employee::factory()->visitor()->create([
            'name' => $name,
            'card' => $card,
            'position' => 'Talaba',
            'department_id' => $this->department->id,
            'group_id' => $groupId === -1 ? $this->participants->id : $groupId,
        ]);
    }

    private function pass(Employee $employee, string $at, int $type = Report::TYPE_ENTRY): void
    {
        Report::factory()->create([
            'employee_id' => $employee->id,
            'department_id' => $employee->department_id,
            'device_id' => $this->device->id,
            'employee_name' => $employee->name,
            'type' => $type,
            'occurred_at' => $at,
        ]);
    }

    private function admin(): User
    {
        return $this->createUserWithPermissions([
            config('menu_permissions.resources')['live'],
            config('menu_permissions.resources')['live-adjust'],
        ]);
    }
}
