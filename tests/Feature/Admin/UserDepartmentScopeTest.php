<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Group;
use App\Models\Report;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class UserDepartmentScopeTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    public function test_non_super_admin_user_requires_department_id(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['users'],
        ]);

        Role::findOrCreate('user', 'web');

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Dept User',
                'email' => 'dept-user@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'roles' => ['user'],
            ])
            ->assertSessionHasErrors('department_id');
    }

    public function test_scoped_user_only_sees_own_department_employees_groups_and_reports(): void
    {
        $own = Department::factory()->create(['name' => 'Own']);
        $other = Department::factory()->create(['name' => 'Other']);

        $schedule = Schedule::factory()->create();

        $ownEmployee = Employee::factory()->create([
            'name' => 'Own Employee',
            'department_id' => $own->id,
            'schedule_id' => $schedule->id,
        ]);
        Employee::factory()->create([
            'name' => 'Other Employee',
            'department_id' => $other->id,
            'schedule_id' => $schedule->id,
        ]);

        Group::factory()->create(['name' => 'Own Group', 'department_id' => $own->id]);
        Group::factory()->create(['name' => 'Other Group', 'department_id' => $other->id]);

        Report::factory()->create([
            'employee_id' => $ownEmployee->id,
            'department_id' => $own->id,
            'employee_name' => 'Own Employee',
        ]);

        $otherEmployee = Employee::factory()->create([
            'name' => 'Other Employee 2',
            'department_id' => $other->id,
            'schedule_id' => $schedule->id,
        ]);
        Report::factory()->create([
            'employee_id' => $otherEmployee->id,
            'department_id' => $other->id,
            'employee_name' => 'Other Employee',
        ]);

        $user = $this->createDepartmentUserWithPermissions($own->id, [
            config('menu_permissions.resources')['employees'],
            config('menu_permissions.resources')['groups'],
            config('menu_permissions.resources')['reports'],
        ]);

        $this->actingAs($user)
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Employees/Index')
                ->has('employees.data', 1)
                ->where('employees.data.0.name', 'Own Employee'));

        $this->actingAs($user)
            ->get(route('admin.groups.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Groups/Index')
                ->has('tree', 1)
                ->where('tree.0.title', 'Own'));

        $this->actingAs($user)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports/Index')
                ->has('reports.data', 1)
                ->where('reports.data.0.employee_name', 'Own Employee'));
    }

    public function test_super_admin_sees_all_departments(): void
    {
        $a = Department::factory()->create();
        $b = Department::factory()->create();
        $schedule = Schedule::factory()->create();

        Employee::factory()->create(['department_id' => $a->id, 'schedule_id' => $schedule->id]);
        Employee::factory()->create(['department_id' => $b->id, 'schedule_id' => $schedule->id]);

        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['employees'],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Employees/Index')
                ->has('employees.data', 2));
    }
}
