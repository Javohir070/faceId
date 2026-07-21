<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class CategoryDeviceAdminTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    public function test_authorized_user_can_manage_categories_and_devices(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['categories'],
            config('menu_permissions.resources')['devices'],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Categories/Index'));

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), [
                'name' => 'Kirish',
            ])
            ->assertRedirect(route('admin.categories.index'));

        $category = Category::query()->where('name', 'Kirish')->first();
        $this->assertNotNull($category);

        $this->actingAs($admin)
            ->get(route('admin.devices.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Devices/Index'));

        $this->actingAs($admin)
            ->post(route('admin.devices.store'), [
                'category_id' => $category->id,
                'name' => 'Terminal 1',
                'description' => 'Asosiy kirish',
                'ip_address' => '192.168.1.10',
                'port' => 80,
                'user' => 'admin',
                'password' => 'secret',
                'type' => 1,
                'autoconnect' => true,
            ])
            ->assertRedirect(route('admin.devices.index'));

        $device = Device::query()->where('name', 'Terminal 1')->first();
        $this->assertNotNull($device);
        $this->assertSame($admin->id, $device->user_id);
        $this->assertSame('secret', $device->password);
        $this->assertTrue($device->autoconnect);

        $this->actingAs($admin)
            ->put(route('admin.devices.update', $device), [
                'category_id' => $category->id,
                'name' => 'Terminal 1 Updated',
                'description' => 'Yangilangan',
                'ip_address' => '192.168.1.11',
                'port' => 8080,
                'user' => 'admin',
                'password' => '',
                'type' => 2,
                'autoconnect' => false,
            ])
            ->assertRedirect(route('admin.devices.index'));

        $device->refresh();
        $this->assertSame('Terminal 1 Updated', $device->name);
        $this->assertSame('secret', $device->password);
        $this->assertFalse($device->autoconnect);

        $this->actingAs($admin)
            ->delete(route('admin.categories.destroy', $category))
            ->assertRedirect();

        $this->assertDatabaseHas('categories', ['id' => $category->id]);

        $this->actingAs($admin)
            ->delete(route('admin.devices.destroy', $device))
            ->assertRedirect(route('admin.devices.index'));

        $this->actingAs($admin)
            ->delete(route('admin.categories.destroy', $category))
            ->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_unauthorized_user_cannot_access_categories_or_devices(): void
    {
        $user = $this->createUserWithPermissions([
            config('menu_permissions.home'),
        ]);

        $this->actingAs($user)
            ->get(route('admin.categories.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('admin.devices.index'))
            ->assertForbidden();
    }
}
