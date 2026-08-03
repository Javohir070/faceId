<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class RolePermissionUserAdminTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    public function test_authorized_user_can_manage_permissions_roles_and_users(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['permissions'],
            config('menu_permissions.resources')['roles'],
            config('menu_permissions.resources')['users'],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.permissions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Permissions/Index'));

        $this->actingAs($admin)
            ->post(route('admin.permissions.store'), [
                'name' => 'Test ruxsat',
            ])
            ->assertRedirect(route('admin.permissions.index'));

        $this->assertDatabaseHas('permissions', ['name' => 'Test ruxsat']);

        $this->actingAs($admin)
            ->get(route('admin.roles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Roles/Index'));

        $this->actingAs($admin)
            ->post(route('admin.roles.store'), [
                'name' => 'editor',
                'permissions' => ['Test ruxsat'],
            ])
            ->assertRedirect(route('admin.roles.index'));

        $role = Role::query()->where('name', 'editor')->first();
        $this->assertNotNull($role);
        $this->assertTrue($role->hasPermissionTo('Test ruxsat'));

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Users/Index'));

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Yangi User',
                'email' => 'new-user@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'department_id' => \App\Models\Department::factory()->create()->id,
                'roles' => ['editor'],
            ])
            ->assertRedirect(route('admin.users.index'));

        $created = User::query()->where('email', 'new-user@example.com')->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->hasRole('editor'));

        $permission = Permission::query()->where('name', 'Test ruxsat')->first();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $created))
            ->assertRedirect(route('admin.users.index'));

        $this->actingAs($admin)
            ->delete(route('admin.roles.destroy', $role))
            ->assertRedirect(route('admin.roles.index'));

        $this->actingAs($admin)
            ->delete(route('admin.permissions.destroy', $permission))
            ->assertRedirect(route('admin.permissions.index'));
    }

    public function test_cannot_delete_user_with_attached_devices(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['users'],
        ]);

        $target = User::factory()->create();
        $category = \App\Models\Category::factory()->create();
        \App\Models\Device::factory()->create([
            'user_id' => $target->id,
            'category_id' => $category->id,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->delete(route('admin.users.destroy', $target))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }
}
