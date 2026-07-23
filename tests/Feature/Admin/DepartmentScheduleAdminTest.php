<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class DepartmentScheduleAdminTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    public function test_authorized_user_can_manage_departments_and_schedules(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['departments'],
            config('menu_permissions.resources')['schedules'],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.departments.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Departments/Index'));

        $this->actingAs($admin)
            ->post(route('admin.departments.store'), [
                'name' => 'IT bo\'limi',
            ])
            ->assertRedirect(route('admin.departments.index'));

        $department = Department::query()->where('name', 'IT bo\'limi')->first();
        $this->assertNotNull($department);

        $this->actingAs($admin)
            ->put(route('admin.departments.update', $department), [
                'name' => 'HR bo\'limi',
            ])
            ->assertRedirect(route('admin.departments.index'));

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'name' => 'HR bo\'limi',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.schedules.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Schedules/Index'));

        $this->actingAs($admin)
            ->post(route('admin.schedules.store'), [
                'name' => 'Bahorgi smena',
                'enter_time' => '09:00',
                'exit_time' => '18:00',
            ])
            ->assertRedirect(route('admin.schedules.index'));

        $schedule = Schedule::query()->where('name', 'Bahorgi smena')->first();
        $this->assertNotNull($schedule);
        $this->assertSame('09:00', $schedule->enter_time->format('H:i'));
        $this->assertSame('18:00', $schedule->exit_time->format('H:i'));

        $this->actingAs($admin)
            ->put(route('admin.schedules.update', $schedule), [
                'name' => 'Yozgi smena',
                'enter_time' => '08:30',
                'exit_time' => '17:30',
            ])
            ->assertRedirect(route('admin.schedules.index'));

        $schedule->refresh();
        $this->assertSame('Yozgi smena', $schedule->name);
        $this->assertSame('08:30', $schedule->enter_time->format('H:i'));

        $this->actingAs($admin)
            ->delete(route('admin.schedules.destroy', $schedule))
            ->assertRedirect(route('admin.schedules.index'));

        $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);

        $this->actingAs($admin)
            ->delete(route('admin.departments.destroy', $department))
            ->assertRedirect(route('admin.departments.index'));

        $this->assertDatabaseMissing('departments', ['id' => $department->id]);
    }

    public function test_unauthorized_user_cannot_access_departments_or_schedules(): void
    {
        $user = $this->createUserWithPermissions([
            config('menu_permissions.home'),
        ]);

        $this->actingAs($user)
            ->get(route('admin.departments.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('admin.schedules.index'))
            ->assertForbidden();
    }

    public function test_cannot_delete_department_or_schedule_with_employees(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['departments'],
            config('menu_permissions.resources')['schedules'],
            config('menu_permissions.resources')['employees'],
        ]);

        $department = Department::factory()->create();
        $schedule = Schedule::factory()->create();
        \App\Models\Employee::factory()->create([
            'department_id' => $department->id,
            'schedule_id' => $schedule->id,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.departments.index'))
            ->delete(route('admin.departments.destroy', $department))
            ->assertRedirect(route('admin.departments.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('departments', ['id' => $department->id]);

        $this->actingAs($admin)
            ->from(route('admin.schedules.index'))
            ->delete(route('admin.schedules.destroy', $schedule))
            ->assertRedirect(route('admin.schedules.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('schedules', ['id' => $schedule->id]);
    }
}
