<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class SyncPageTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    public function test_authorized_user_can_view_sync_page_and_transfer_employees(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['sync'],
        ]);

        $category = Category::factory()->create();
        $department = Department::factory()->create();
        $schedule = Schedule::factory()->create();

        $device = Device::factory()->create([
            'user_id' => $admin->id,
            'category_id' => $category->id,
            'is_online' => true,
        ]);

        $employee = Employee::factory()->create([
            'department_id' => $department->id,
            'schedule_id' => $schedule->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.sync.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Sync/Index')
                ->has('devices', 1)
                ->has('employees', 1));

        $this->actingAs($admin)
            ->post(route('admin.sync.transfer'), [
                'device_ids' => [$device->id],
                'employee_ids' => [$employee->id],
            ])
            ->assertRedirect(route('admin.sync.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('device_employee', [
            'device_id' => $device->id,
            'employee_id' => $employee->id,
        ]);

        $employee->refresh();
        $this->assertSame(1, $employee->devices()->count());
        $this->assertSame(1, $employee->devices()->whereNotNull('device_employee.synced_at')->count());
    }

    public function test_transfer_skips_offline_devices_when_online_exists(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['sync'],
        ]);

        $category = Category::factory()->create();
        $department = Department::factory()->create();
        $schedule = Schedule::factory()->create();

        $online = Device::factory()->create([
            'user_id' => $admin->id,
            'category_id' => $category->id,
            'name' => 'Online',
            'is_online' => true,
            'ip_address' => '10.0.0.1',
        ]);
        $offline = Device::factory()->create([
            'user_id' => $admin->id,
            'category_id' => $category->id,
            'name' => 'Offline',
            'is_online' => false,
            'ip_address' => '10.0.0.2',
        ]);
        $employee = Employee::factory()->create([
            'department_id' => $department->id,
            'schedule_id' => $schedule->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.sync.transfer'), [
                'device_ids' => [$online->id, $offline->id],
                'employee_ids' => [$employee->id],
            ])
            ->assertRedirect(route('admin.sync.index'));

        $this->assertDatabaseHas('device_employee', [
            'device_id' => $online->id,
            'employee_id' => $employee->id,
        ]);
        $this->assertDatabaseMissing('device_employee', [
            'device_id' => $offline->id,
            'employee_id' => $employee->id,
        ]);
    }

    public function test_unauthorized_user_cannot_access_sync_page(): void
    {
        $user = $this->createUserWithPermissions([
            config('menu_permissions.home'),
        ]);

        $this->actingAs($user)
            ->get(route('admin.sync.index'))
            ->assertForbidden();
    }
}
