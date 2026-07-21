<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class DeviceActionTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    public function test_authorized_user_can_connect_device(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push(['DeviceInfo' => ['deviceName' => 'Terminal']], 200)
                ->push('', 200)
                ->push('', 200),
        ]);

        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['devices'],
        ]);

        $category = Category::factory()->create();
        $device = Device::factory()->create([
            'user_id' => $admin->id,
            'category_id' => $category->id,
            'ip_address' => '192.168.1.50',
            'port' => 8000,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.devices.connect', $device))
            ->assertRedirect()
            ->assertSessionHas('success');

        $device->refresh();
        $this->assertTrue($device->is_online);
        $this->assertNotNull($device->last_connected_at);
    }

    public function test_unauthorized_user_cannot_run_device_actions(): void
    {
        $user = $this->createUserWithPermissions([
            config('menu_permissions.home'),
        ]);

        $owner = User::factory()->create();
        $category = Category::factory()->create();
        $device = Device::factory()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
        ]);

        $this->actingAs($user)
            ->post(route('admin.devices.connect', $device))
            ->assertForbidden();
    }
}
