<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Group;
use App\Models\Report;
use App\Models\User;
use App\Models\VisitAdjustment;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class VisitAdjustmentTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    private Department $department;

    private Group $participants;

    private Group $sponsors;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setTime(11, 0));
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->department = Department::factory()->create(['name' => 'Innoweek 2026']);
        $this->participants = Group::factory()->create(['name' => 'Ishtirokchi', 'department_id' => $this->department->id]);
        $this->sponsors = Group::factory()->create(['name' => 'Homiy', 'department_id' => $this->department->id]);
    }

    public function test_page_requires_permission_and_defaults_to_participants_group(): void
    {
        $viewer = $this->createUserWithPermissions([config('menu_permissions.resources')['live']]);

        $this->actingAs($viewer)->get(route('admin.live.adjustments.index'))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.live.adjustments.store'), [
            'date' => now()->toDateString(),
            'entered' => 10,
        ])->assertForbidden();

        $this->actingAs($this->admin())
            ->get(route('admin.live.adjustments.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Live/Adjustments')
                ->where('date', now()->toDateString())
                ->where('defaultGroupId', $this->participants->id)
                ->has('groups', 2)
                ->where('totals.entered', 0));
    }

    public function test_added_entries_change_live_stats_consistently(): void
    {
        $admin = $this->admin();

        // Haqiqiy bitta kirish (xorijiy ayol, Homiy) — qo‘shilganlar unga qo‘shiladi.
        $guest = Employee::factory()->visitor()->create([
            'department_id' => $this->department->id,
            'group_id' => $this->sponsors->id,
            'gender' => Employee::GENDER_FEMALE,
            'country' => 'KZ',
        ]);
        Report::query()->create([
            'employee_id' => $guest->id,
            'department_id' => $guest->department_id,
            'employee_name' => $guest->name,
            'type' => Report::TYPE_ENTRY,
            'occurred_at' => now()->subHours(2),
        ]);

        foreach ([1000, 500] as $count) {
            $this->actingAs($admin)
                ->post(route('admin.live.adjustments.store'), [
                    'date' => now()->toDateString(),
                    'entered' => $count,
                    'group_id' => $this->participants->id,
                    'note' => '2-darvoza',
                ])
                ->assertRedirect(route('admin.live.adjustments.index', ['date' => now()->toDateString()]))
                ->assertSessionHas('success');
        }

        $this->assertDatabaseHas('visit_adjustments', ['entered' => 1000, 'male' => 700, 'female' => 300, 'department_id' => $this->department->id]);
        $this->assertDatabaseHas('visit_adjustments', ['entered' => 500, 'male' => 350, 'female' => 150]);

        $this->actingAs($admin)
            ->get(route('admin.live.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.entered', 1501)
                ->where('stats.male', 1050)
                ->where('stats.female', 451)
                ->where('stats.local', 1500)
                ->where('stats.foreign', 1)
                ->where('stats.groups.0.name', 'Ishtirokchi')
                ->where('stats.groups.0.entered', 1500)
                ->where('stats.groups.1.name', 'Homiy')
                ->where('stats.groups.1.entered', 1)
                // O‘tishlar ro‘yxati faqat haqiqiy kirishlar.
                ->has('passes', 1));

        $this->actingAs($admin)
            ->get(route('admin.live.stats'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.entered', 1501)
                ->where('stats.entered_last_hour', 1500)
                ->where('stats.male', 1050)
                ->where('stats.female', 451)
                ->where('stats.local', 1500)
                ->where('stats.foreign', 1)
                ->where('stats.inside', 1)
                ->where('stats.registered', 1));

        // Ertasi kuni qo‘shilganlar amal qilmaydi.
        $this->travel(1)->days();

        $this->actingAs($admin)
            ->get(route('admin.live.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.entered', 0)
                ->where('stats.local', 0)
                ->where('stats.groups.0.entered', 0));
    }

    public function test_adjustment_can_be_deleted(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.live.adjustments.store'), [
            'date' => now()->toDateString(),
            'entered' => 1000,
            'group_id' => $this->participants->id,
        ]);
        $adjustment = VisitAdjustment::query()->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('admin.live.adjustments.destroy', $adjustment))
            ->assertRedirect(route('admin.live.adjustments.index', ['date' => now()->toDateString()]));

        $this->assertDatabaseCount('visit_adjustments', 0);
        $this->actingAs($admin)
            ->get(route('admin.live.index'))
            ->assertInertia(fn (Assert $page) => $page->where('stats.entered', 0));
    }

    public function test_validation_and_department_scope(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.live.adjustments.store'), ['date' => now()->toDateString(), 'entered' => 0])
            ->assertSessionHasErrors('entered');

        $other = Department::factory()->create();
        $otherGroup = Group::factory()->create(['department_id' => $other->id]);
        $scoped = $this->createDepartmentUserWithPermissions($this->department->id, [
            config('menu_permissions.resources')['live'],
            config('menu_permissions.resources')['live-adjust'],
        ]);

        $this->actingAs($scoped)
            ->post(route('admin.live.adjustments.store'), [
                'date' => now()->toDateString(),
                'entered' => 100,
                'group_id' => $otherGroup->id,
            ])
            ->assertSessionHasErrors('group_id');

        VisitAdjustment::query()->create([
            'date' => now()->toDateString(),
            'department_id' => $other->id,
            'group_id' => $otherGroup->id,
            'entered' => 999,
            'male' => 699,
            'female' => 300,
        ]);

        // Boshqa bo‘lim qo‘shgani bu bo‘lim foydalanuvchisining statistikasiga tushmaydi.
        $this->actingAs($scoped)
            ->get(route('admin.live.index'))
            ->assertInertia(fn (Assert $page) => $page->where('stats.entered', 0));
    }

    private function admin(): User
    {
        return $this->createUserWithPermissions([
            config('menu_permissions.resources')['live'],
            config('menu_permissions.resources')['live-adjust'],
        ]);
    }
}
