<?php

namespace Tests\Feature\Admin;

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
