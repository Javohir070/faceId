<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class GroupAdminTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    public function test_authorized_user_can_manage_groups(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['groups'],
        ]);

        $department = Department::factory()->create(['name' => 'IT']);

        $this->actingAs($admin)
            ->get(route('admin.groups.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Groups/Index')
                ->has('tree'));

        $this->actingAs($admin)
            ->post(route('admin.groups.store'), [
                'name' => 'Backend',
                'department_id' => $department->id,
                'parent_id' => null,
            ])
            ->assertRedirect(route('admin.groups.index'));

        $parent = Group::query()->where('name', 'Backend')->first();
        $this->assertNotNull($parent);

        $this->actingAs($admin)
            ->get(route('admin.groups.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Groups/Index')
                ->has('tree', 1)
                ->where('tree.0.type', 'department')
                ->where('tree.0.title', 'IT')
                ->where('tree.0.children.0.title', 'Backend'));

        $this->actingAs($admin)
            ->post(route('admin.groups.store'), [
                'name' => 'API',
                'department_id' => $department->id,
                'parent_id' => $parent->id,
            ])
            ->assertRedirect(route('admin.groups.index'));

        $child = Group::query()->where('name', 'API')->first();
        $this->assertNotNull($child);
        $this->assertSame($parent->id, $child->parent_id);

        $this->actingAs($admin)
            ->put(route('admin.groups.update', $child), [
                'name' => 'API Team',
                'department_id' => $department->id,
                'parent_id' => $parent->id,
            ])
            ->assertRedirect(route('admin.groups.index'));

        $this->assertDatabaseHas('groups', [
            'id' => $child->id,
            'name' => 'API Team',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.groups.destroy', $child))
            ->assertRedirect(route('admin.groups.index'));

        $this->assertDatabaseMissing('groups', ['id' => $child->id]);
    }

    public function test_index_tree_includes_employee_counts(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['groups'],
        ]);

        $it = Department::factory()->create(['name' => 'IT']);
        $backend = Group::factory()->create(['name' => 'Backend', 'department_id' => $it->id]);
        $api = Group::factory()->childOf($backend)->create(['name' => 'API']);
        Employee::factory()->count(2)->create(['department_id' => $it->id, 'group_id' => $backend->id]);
        Employee::factory()->create(['department_id' => $it->id, 'group_id' => $api->id]);
        Employee::factory()->create(['department_id' => $it->id, 'group_id' => null]);

        $this->actingAs($admin)
            ->get(route('admin.groups.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tree.0.employees_count', 4)
                ->where('tree.0.unassigned_count', 1)
                ->where('tree.0.children.0.title', 'Backend')
                ->where('tree.0.children.0.employees_count', 2)
                ->where('tree.0.children.0.children.0.title', 'API')
                ->where('tree.0.children.0.children.0.employees_count', 1));
    }

    public function test_show_page_lists_path_subgroups_and_employees(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['groups'],
        ]);

        $it = Department::factory()->create(['name' => 'IT']);
        $backend = Group::factory()->create(['name' => 'Backend', 'department_id' => $it->id]);
        $api = Group::factory()->childOf($backend)->create(['name' => 'API']);
        $auth = Group::factory()->childOf($api)->create(['name' => 'Auth']);
        Employee::factory()->create(['name' => 'Zafar', 'department_id' => $it->id, 'group_id' => $api->id]);
        Employee::factory()->create(['name' => 'Aziz', 'department_id' => $it->id, 'group_id' => $api->id]);
        Employee::factory()->create(['department_id' => $it->id, 'group_id' => $auth->id]);

        $this->actingAs($admin)
            ->get(route('admin.groups.show', $api))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Groups/Show')
                ->where('group.name', 'API')
                ->where('group.department.name', 'IT')
                ->has('group.path', 1)
                ->where('group.path.0.name', 'Backend')
                ->has('group.children', 1)
                ->where('group.children.0.name', 'Auth')
                ->where('group.children.0.employees_count', 1)
                ->where('group.employees_count', 2)
                ->has('group.employees', 2)
                ->where('group.employees.0.name', 'Aziz'));
    }

    public function test_create_form_is_prefilled_from_query(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['groups'],
        ]);

        $it = Department::factory()->create();
        $hr = Department::factory()->create();
        $backend = Group::factory()->create(['department_id' => $it->id]);

        $this->actingAs($admin)
            ->get(route('admin.groups.create', ['parent_id' => $backend->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('defaults.department_id', $it->id)
                ->where('defaults.parent_id', $backend->id));

        $this->actingAs($admin)
            ->get(route('admin.groups.create', ['department_id' => $hr->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('defaults.department_id', $hr->id)
                ->where('defaults.parent_id', null));

        $this->actingAs($admin)
            ->get(route('admin.groups.create', ['department_id' => 999999, 'parent_id' => 999999]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('defaults.department_id', null)
                ->where('defaults.parent_id', null));
    }

    public function test_unauthorized_user_cannot_access_groups(): void
    {
        $user = $this->createUserWithPermissions([
            config('menu_permissions.home'),
        ]);

        $this->actingAs($user)
            ->get(route('admin.groups.index'))
            ->assertForbidden();
    }

    public function test_cannot_delete_group_with_children(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['groups'],
        ]);

        $parent = Group::factory()->create();
        Group::factory()->childOf($parent)->create();

        $this->actingAs($admin)
            ->delete(route('admin.groups.destroy', $parent))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('groups', ['id' => $parent->id]);
    }

    public function test_parent_must_belong_to_same_department(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['groups'],
        ]);

        $it = Department::factory()->create();
        $hr = Department::factory()->create();
        $parent = Group::factory()->create(['department_id' => $it->id]);

        $this->actingAs($admin)
            ->post(route('admin.groups.store'), [
                'name' => 'Wrong parent',
                'department_id' => $hr->id,
                'parent_id' => $parent->id,
            ])
            ->assertSessionHasErrors('parent_id');
    }
}
