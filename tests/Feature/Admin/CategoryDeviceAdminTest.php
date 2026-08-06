<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Device;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Devices/Index')
                ->has('devices.data', 0)
                ->has('filters')
                ->has('categories'));

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
            ->get(route('admin.devices.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Devices/Index')
                ->has('devices.data', 1)
                ->where('devices.data.0.name', 'Terminal 1')
                ->where('devices.data.0.has_password', true)
                ->missing('devices.data.0.password'));

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

    public function test_device_index_filters_by_category_type_and_status(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['devices'],
        ]);

        $entryCategory = Category::factory()->create(['name' => 'Kirish']);
        $exitCategory = Category::factory()->create(['name' => 'Chiqish']);

        Device::factory()->create([
            'user_id' => $admin->id,
            'category_id' => $entryCategory->id,
            'name' => 'Entry Online',
            'type' => Device::TYPE_ENTRY,
            'is_online' => true,
            'ip_address' => '10.0.0.1',
        ]);
        Device::factory()->create([
            'user_id' => $admin->id,
            'category_id' => $exitCategory->id,
            'name' => 'Exit Offline',
            'type' => Device::TYPE_EXIT,
            'is_online' => false,
            'ip_address' => '10.0.0.2',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.devices.index', [
                'search' => 'Entry',
                'category_id' => $entryCategory->id,
                'type' => Device::TYPE_ENTRY,
                'is_online' => '1',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Devices/Index')
                ->where('filters.search', 'Entry')
                ->where('filters.category_id', $entryCategory->id)
                ->where('filters.type', Device::TYPE_ENTRY)
                ->where('filters.is_online', '1')
                ->has('devices.data', 1)
                ->where('devices.data.0.name', 'Entry Online'));
    }

    public function test_device_show_includes_linked_employees(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['devices'],
        ]);

        $device = Device::factory()->create([
            'user_id' => $admin->id,
            'name' => 'Gate A',
        ]);

        $employee = Employee::factory()->create([
            'name' => 'Ali Valiyev',
            'card' => '11223344',
        ]);

        $device->employees()->attach($employee->id, [
            'synced_at' => now(),
            'sync_error' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.devices.show', $device))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Devices/Show')
                ->has('device.employees', 1)
                ->where('device.employees.0.name', 'Ali Valiyev')
                ->where('device.employees.0.card', '11223344')
                ->where('device.employees.0.is_synced', true));
    }

    public function test_device_can_remove_selected_employees(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['devices'],
        ]);

        $device = Device::factory()->create([
            'user_id' => $admin->id,
            'name' => 'Gate B',
            'is_online' => true,
        ]);

        $employee = Employee::factory()->create([
            'name' => 'Dilnoza Karimova',
            'card' => '55667788',
        ]);

        $device->employees()->attach($employee->id, [
            'synced_at' => now(),
        ]);

        $this->actingAs($admin)
            ->from(route('admin.devices.show', $device))
            ->post(route('admin.devices.remove-employees', $device), [
                'employee_ids' => [$employee->id],
            ])
            ->assertRedirect(route('admin.devices.show', $device))
            ->assertSessionHas('success', 'Tanlangan xodimlar qurilmadan o‘chirildi.');

        $this->assertDatabaseMissing('device_employee', [
            'device_id' => $device->id,
            'employee_id' => $employee->id,
        ]);
    }
}
