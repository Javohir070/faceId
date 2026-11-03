<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    public function test_guest_is_redirected_from_dashboard_to_login(): void
    {
        $this->get(route('home.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_dashboard(): void
    {
        $user = $this->createUserWithPermissions();

        $this->actingAs($user)
            ->get(route('home.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Home')
                ->has('stats')
                ->has('devices')
            );
    }

    public function test_dashboard_lists_devices_without_credentials(): void
    {
        $user = $this->createUserWithPermissions();

        $second = Device::factory()->create(['name' => 'Chiqish', 'order' => 2, 'type' => Device::TYPE_EXIT, 'is_online' => false]);
        $first = Device::factory()->create(['name' => 'Kirish', 'order' => 1, 'type' => Device::TYPE_ENTRY, 'is_online' => true]);

        $this->actingAs($user)
            ->get(route('home.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('devices', 2)
                ->where('devices.0.id', $first->id)
                ->where('devices.0.type_label', 'Kirish')
                ->where('devices.0.is_online', true)
                ->where('devices.1.id', $second->id)
                ->where('devices.1.is_online', false)
                ->missing('devices.0.password')
                ->missing('devices.0.user')
            );
    }

    public function test_dashboard_hides_devices_without_device_permission(): void
    {
        Device::factory()->create();

        $user = $this->createUserWithPermissions();
        $user->removeRole('super-admin');
        $user->revokePermissionTo(config('menu_permissions.resources.devices'));

        $this->actingAs($user)
            ->get(route('home.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('devices', 0));
    }

    public function test_dashboard_shows_scoped_user_only_their_devices(): void
    {
        $user = $this->createDepartmentUserWithPermissions(Department::factory()->create()->id);

        $own = Device::factory()->create(['user_id' => $user->id]);
        Device::factory()->create();

        $this->actingAs($user)
            ->get(route('home.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('devices', 1)
                ->where('devices.0.id', $own->id)
                ->where('stats.departments', 1)
            );
    }

    public function test_authenticated_user_visiting_login_is_redirected_to_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('login'))
            ->assertRedirect('/dashboard');
    }

    public function test_admin_directory_route_is_not_used_for_dashboard(): void
    {
        $this->assertSame('/dashboard', route('home.index', absolute: false));
    }
}
