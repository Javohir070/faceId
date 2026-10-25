<?php

namespace Tests\Feature\Admin;

use App\Models\AccessControllerEvent;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Face;
use App\Models\Group;
use App\Models\Report;
use App\Models\Schedule;
use App\Services\DepartmentService;
use App\Services\ScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

/**
 * Tadbir mehmonlari (is_visitor) faqat Jonli kuzatuv va Tashrif statistikasida ko‘rinadi.
 */
class VisitorVisibilityTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private Employee $staff;

    private Employee $visitor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setTime(11, 0));

        $group = Group::factory()->create(['name' => 'Ishtirokchi']);
        $this->staff = Employee::factory()->create(['name' => 'Oddiy Xodim', 'card' => '1001']);
        $this->visitor = Employee::factory()->visitor()->create([
            'name' => 'Innoweek Mehmon',
            'card' => '0003700000',
            'department_id' => $group->department_id,
            'group_id' => $group->id,
            'gender' => Employee::GENDER_FEMALE,
            'country' => 'KR',
        ]);
        Face::query()->create(['employee_id' => $this->visitor->id, 'face' => self::PNG_BASE64]);

        $device = Device::factory()->create(['type' => Device::TYPE_ENTRY]);

        foreach ([$this->staff, $this->visitor] as $employee) {
            // ReportFactory o‘zi qo‘shimcha xodim yaratadi — sonlar aniq bo‘lishi uchun to‘g‘ridan-to‘g‘ri.
            Report::query()->create([
                'employee_id' => $employee->id,
                'department_id' => $employee->department_id,
                'device_id' => $device->id,
                'employee_name' => $employee->name,
                'type' => Report::TYPE_ENTRY,
                'occurred_at' => now()->subMinutes(10),
            ]);
            AccessControllerEvent::factory()->create([
                'employee_no' => $employee->card,
                'full_name' => $employee->name,
                'event_time' => now()->subMinutes(10),
            ]);
        }
    }

    public function test_visitors_are_hidden_from_regular_pages(): void
    {
        $admin = $this->createUserWithPermissions();

        $this->actingAs($admin)
            ->get(route('admin.employees.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('employees.data', 1)
                ->where('employees.data.0.id', $this->staff->id));

        $this->actingAs($admin)
            ->get(route('admin.employees.show', $this->visitor))
            ->assertNotFound();

        $this->actingAs($admin)
            ->get(route('admin.sync.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('employees', 1)
                ->where('employees.0.id', $this->staff->id));

        $this->actingAs($admin)
            ->get(route('admin.attendance.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 1)
                ->where('rows.data.0.id', $this->staff->id));

        $this->actingAs($admin)
            ->get(route('admin.reports.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('reports.data', 1)
                ->where('reports.data.0.employee_name', 'Oddiy Xodim'));

        $this->actingAs($admin)
            ->get(route('admin.notes.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('employees', 1)
                ->where('employees.0.id', $this->staff->id));

        $this->actingAs($admin)
            ->get(route('home.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.employees', 1)
                ->has('recentEvents', 1)
                ->where('recentEvents.0.full_name', 'Oddiy Xodim'));
    }

    public function test_live_pages_include_visitors(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['live'],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.live.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('passes', 2)
                ->where('passes.0.employee_name', 'Innoweek Mehmon')
                ->where('passes.0.group_name', 'Ishtirokchi')
                ->where('passes.0.face_url', route('admin.live.face', $this->visitor->id))
                ->where('stats.entered', 2)
                ->where('stats.foreign', 1)
                ->where('stats.groups.0.entered', 1));

        $this->actingAs($admin)
            ->get(route('admin.live.stats'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.registered', 2)
                ->where('stats.entered', 2)
                ->where('stats.inside', 2)
                ->where('stats.female', $this->staff->gender === Employee::GENDER_FEMALE ? 2 : 1)
                ->where('stats.foreign', 1)
                ->where('stats.last_arrival.employee_name', 'Innoweek Mehmon'));

        $this->actingAs($admin)
            ->get(route('admin.live.face', $this->visitor->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_department_and_schedule_with_only_visitors_cannot_be_deleted(): void
    {
        $department = Department::query()->findOrFail($this->visitor->department_id);
        $schedule = Schedule::query()->findOrFail($this->visitor->schedule_id);

        $this->assertNotNull(app(DepartmentService::class)->delete($department));
        $this->assertNotNull(app(ScheduleService::class)->delete($schedule));
        $this->assertDatabaseHas('departments', ['id' => $department->id]);
        $this->assertDatabaseHas('schedules', ['id' => $schedule->id]);
    }
}
