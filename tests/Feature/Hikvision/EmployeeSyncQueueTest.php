<?php

namespace Tests\Feature\Hikvision;

use App\Jobs\Hikvision\RemoveHikvisionUsersJob;
use App\Jobs\Hikvision\SyncEmployeeJob;
use App\Jobs\Hikvision\TransferEmployeesJob;
use App\Models\Category;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class EmployeeSyncQueueTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    public function test_creating_employee_does_not_auto_sync_to_devices(): void
    {
        Storage::fake('local');
        Queue::fake();

        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['employees'],
        ]);

        $category = Category::factory()->create();
        $department = Department::factory()->create();
        $schedule = Schedule::factory()->create();
        Device::factory()->create([
            'user_id' => $admin->id,
            'category_id' => $category->id,
            'is_online' => true,
            'ip_address' => '192.168.1.10',
            'port' => 80,
            'user' => 'admin',
            'password' => 'secret',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.employees.store'), [
                'name' => 'Yangi Xodim',
                'card' => '99887766',
                'gender' => 1,
                'department_id' => $department->id,
                'schedule_id' => $schedule->id,
                'valid_from_dt' => '2026-01-01T09:00',
                'valid_to_dt' => '2026-12-31T18:00',
                'face' => UploadedFile::fake()->image('face.jpg', 200, 200),
            ])
            ->assertRedirect(route('admin.employees.index'));

        $employee = Employee::query()->where('card', '99887766')->first();
        $this->assertNotNull($employee);
        $this->assertSame(0, $employee->devices()->count());
        Queue::assertNotPushed(SyncEmployeeJob::class);
        Queue::assertNotPushed(TransferEmployeesJob::class);
    }

    public function test_sync_transfer_pushes_employees_immediately(): void
    {
        Queue::fake();
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
        $employee->face()->create([
            'face' => base64_encode('fake-face-bytes'),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.sync.transfer'), [
                'device_ids' => [$device->id],
                'employee_ids' => [$employee->id],
            ])
            ->assertRedirect(route('admin.sync.index'))
            ->assertSessionHas('success');

        Queue::assertNotPushed(TransferEmployeesJob::class);

        $this->assertDatabaseHas('device_employee', [
            'device_id' => $device->id,
            'employee_id' => $employee->id,
        ]);
        $this->assertNotNull(
            $employee->devices()->where('devices.id', $device->id)->first()?->pivot->synced_at,
        );
    }

    public function test_transfer_skips_employees_without_face(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['sync'],
        ]);

        $device = Device::factory()->create([
            'user_id' => $admin->id,
            'category_id' => Category::factory(),
            'is_online' => true,
        ]);
        $employee = Employee::factory()->create([
            'department_id' => Department::factory(),
            'schedule_id' => Schedule::factory(),
        ]);

        $result = app(\App\Services\Hikvision\EmployeeSyncService::class)
            ->transfer([$device->id], [$employee->id]);

        $this->assertSame(0, $result['success']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame(1, $result['skipped']);
        $this->assertNull(
            $employee->devices()->where('devices.id', $device->id)->first()?->pivot->synced_at,
        );
        $this->assertSame(
            'Yuz rasmi yo‘q — sinxronlanmadi',
            $employee->devices()->where('devices.id', $device->id)->first()?->pivot->sync_error,
        );
        Http::assertNothingSent();
    }

    public function test_deleting_employee_queues_device_removal_by_card(): void
    {
        Queue::fake();

        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['employees'],
        ]);

        $category = Category::factory()->create();
        $department = Department::factory()->create();
        $schedule = Schedule::factory()->create();
        $device = Device::factory()->create([
            'user_id' => $admin->id,
            'category_id' => $category->id,
        ]);
        $employee = Employee::factory()->create([
            'department_id' => $department->id,
            'schedule_id' => $schedule->id,
            'card' => '55667788',
        ]);
        $employee->devices()->attach($device->id, ['synced_at' => now()]);

        $this->actingAs($admin)
            ->delete(route('admin.employees.destroy', $employee))
            ->assertRedirect(route('admin.employees.index'));

        $this->assertDatabaseMissing('employees', ['id' => $employee->id]);

        Queue::assertPushed(RemoveHikvisionUsersJob::class, function (RemoveHikvisionUsersJob $job) use ($device): bool {
            return $job->employeeCard === '55667788'
                && $job->deviceIds === [$device->id]
                && $job->employeeId === null;
        });
    }

    public function test_device_sync_only_pushes_linked_employees(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['devices'],
        ]);

        $category = Category::factory()->create();
        $department = Department::factory()->create();
        $schedule = Schedule::factory()->create();
        $device = Device::factory()->create([
            'user_id' => $admin->id,
            'category_id' => $category->id,
            'ip_address' => '192.168.1.20',
            'port' => 80,
            'user' => 'admin',
            'password' => 'secret',
        ]);

        $linked = Employee::factory()->create([
            'department_id' => $department->id,
            'schedule_id' => $schedule->id,
            'card' => '11112222',
        ]);
        $linked->face()->create(['face' => base64_encode('linked-face')]);
        $unlinked = Employee::factory()->create([
            'department_id' => $department->id,
            'schedule_id' => $schedule->id,
            'card' => '33334444',
        ]);
        $unlinked->face()->create(['face' => base64_encode('unlinked-face')]);
        $linked->devices()->attach($device->id);

        $result = app(\App\Services\Hikvision\EmployeeSyncService::class)->syncAllEmployees($device);

        $this->assertSame(1, $result['success']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame(0, $result['skipped']);
        $this->assertNotNull(
            $linked->devices()->where('devices.id', $device->id)->first()?->pivot->synced_at,
        );
        $this->assertSame(0, $unlinked->devices()->count());
    }

    public function test_device_sync_skips_linked_employees_without_face(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $device = Device::factory()->create([
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'is_online' => true,
        ]);
        $noFace = Employee::factory()->create([
            'card' => '99998888',
        ]);
        $noFace->devices()->attach($device->id);

        $result = app(\App\Services\Hikvision\EmployeeSyncService::class)->syncAllEmployees($device);

        $this->assertSame(0, $result['success']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame(1, $result['skipped']);
        Http::assertNothingSent();
    }

    public function test_device_sync_runs_immediately_for_linked_employees(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['devices'],
        ]);

        $category = Category::factory()->create();
        $department = Department::factory()->create();
        $schedule = Schedule::factory()->create();
        $device = Device::factory()->create([
            'user_id' => $admin->id,
            'category_id' => $category->id,
            'ip_address' => '192.168.1.21',
            'port' => 80,
            'user' => 'admin',
            'password' => 'secret',
            'is_online' => true,
        ]);

        $linked = Employee::factory()->create([
            'department_id' => $department->id,
            'schedule_id' => $schedule->id,
            'card' => '55556666',
        ]);
        $linked->face()->create(['face' => base64_encode('device-sync-face')]);
        $linked->devices()->attach($device->id);

        $this->actingAs($admin)
            ->from(route('admin.devices.show', $device))
            ->post(route('admin.devices.sync-employees', $device))
            ->assertRedirect(route('admin.devices.show', $device))
            ->assertSessionHas('success');

        $this->assertNotNull(
            $linked->devices()->where('devices.id', $device->id)->first()?->pivot->synced_at,
        );
    }
}
