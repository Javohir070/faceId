<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
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
