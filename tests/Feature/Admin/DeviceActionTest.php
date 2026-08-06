<?php

namespace Tests\Feature\Admin;

use App\Jobs\Hikvision\ClearDeviceUsersJob;
use App\Jobs\Hikvision\RemoveHikvisionUsersJob;
use App\Models\Category;
use App\Models\Device;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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
        $this->assertTrue($device->webhook_configured);
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

    public function test_authorized_user_can_pull_employees_from_device(): void
    {
        Http::fake([
            '*UserInfo/Search*' => Http::response([
                'UserInfoSearch' => [
                    'responseStatusStrg' => 'OK',
                    'UserInfo' => [
                        [
                            'employeeNo' => '9001',
                            'name' => 'Pulled User',
                            'Valid' => [
                                'beginTime' => '2026-01-01T00:00:00',
                                'endTime' => '2030-12-31T23:59:59',
                            ],
                        ],
                    ],
                ],
            ], 200),
            '*FDSearch*' => Http::response('', 404),
            '*picture*' => Http::response('', 404),
        ]);

        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['devices'],
        ]);

        $device = Device::factory()->create([
            'user_id' => $admin->id,
            'category_id' => Category::factory(),
            'is_online' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.devices.pull-employees', $device))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('employees', [
            'card' => '9001',
            'name' => 'Pulled User',
        ]);
        $this->assertTrue($device->employees()->where('card', '9001')->exists());
    }

    public function test_pull_employees_rejects_offline_device(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['devices'],
        ]);

        $device = Device::factory()->create([
            'user_id' => $admin->id,
            'category_id' => Category::factory(),
            'is_online' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.devices.pull-employees', $device))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_clear_removes_employees_immediately_without_queue(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        Queue::fake();

        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['devices'],
        ]);

        $device = Device::factory()->create([
            'user_id' => $admin->id,
            'category_id' => Category::factory(),
            'is_online' => true,
        ]);
        $employee = Employee::factory()->create();
        $device->employees()->attach($employee->id, ['synced_at' => now()]);

        $this->actingAs($admin)
            ->post(route('admin.devices.clear', $device))
            ->assertRedirect()
            ->assertSessionHas('success', 'Qurilmadagi xodimlar o‘chirildi.');

        Queue::assertNotPushed(ClearDeviceUsersJob::class);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/ISAPI/AccessControl/UserInfo/Delete'));

        $this->assertDatabaseMissing('device_employee', [
            'device_id' => $device->id,
            'employee_id' => $employee->id,
        ]);
    }

    public function test_remove_employees_runs_immediately_without_queue(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        Queue::fake();

        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['devices'],
        ]);

        $device = Device::factory()->create([
            'user_id' => $admin->id,
            'category_id' => Category::factory(),
            'is_online' => true,
        ]);
        $employee = Employee::factory()->create();
        $device->employees()->attach($employee->id, ['synced_at' => now()]);

        $this->actingAs($admin)
            ->post(route('admin.devices.remove-employees', $device), [
                'employee_ids' => [$employee->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Tanlangan xodimlar qurilmadan o‘chirildi.');

        Queue::assertNotPushed(RemoveHikvisionUsersJob::class);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/ISAPI/AccessControl/UserInfo/Delete'));

        $this->assertDatabaseMissing('device_employee', [
            'device_id' => $device->id,
            'employee_id' => $employee->id,
        ]);
    }
}
