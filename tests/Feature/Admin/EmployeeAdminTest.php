<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Face;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class EmployeeAdminTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    public function test_authorized_user_can_manage_employees(): void
    {
        Storage::fake('local');

        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['employees'],
        ]);

        $department = Department::factory()->create(['name' => 'IT']);
        $schedule = Schedule::factory()->create(['name' => 'Asosiy']);
        $face = UploadedFile::fake()->image('face.jpg', 200, 200);

        $this->actingAs($admin)
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Employees/Index'));

        $this->actingAs($admin)
            ->post(route('admin.employees.store'), [
                'name' => 'Ali Valiyev',
                'card' => '1234567890',
                'department_id' => $department->id,
                'schedule_id' => $schedule->id,
                'valid_from_dt' => '2026-01-01T09:00',
                'valid_to_dt' => '2026-12-31T18:00',
                'face' => $face,
            ])
            ->assertRedirect(route('admin.employees.index'));

        $employee = Employee::query()->where('card', '1234567890')->first();
        $this->assertNotNull($employee);
        $this->assertSame('Ali Valiyev', $employee->name);
        $this->assertSame($department->id, $employee->department_id);
        $this->assertSame($schedule->id, $employee->schedule_id);
        $this->assertDatabaseHas('faces', ['employee_id' => $employee->id]);
        $this->assertNotEmpty($employee->face?->face);

        $replacementFace = UploadedFile::fake()->image('face-2.jpg', 180, 180);

        $this->actingAs($admin)
            ->put(route('admin.employees.update', $employee), [
                'name' => 'Ali Valiyev Updated',
                'card' => '1234567890',
                'department_id' => $department->id,
                'schedule_id' => $schedule->id,
                'valid_from_dt' => '2026-02-01T09:00',
                'valid_to_dt' => '2027-01-31T18:00',
                'face' => $replacementFace,
            ])
            ->assertRedirect(route('admin.employees.index'));

        $employee->refresh();
        $this->assertSame('Ali Valiyev Updated', $employee->name);
        $this->assertSame(1, Face::query()->where('employee_id', $employee->id)->count());

        $this->actingAs($admin)
            ->put(route('admin.employees.update', $employee), [
                'name' => 'Ali Valiyev Updated',
                'card' => '1234567890',
                'department_id' => $department->id,
                'schedule_id' => $schedule->id,
                'valid_from_dt' => '2026-02-01T09:00',
                'valid_to_dt' => '2027-01-31T18:00',
                'remove_face' => true,
            ])
            ->assertRedirect(route('admin.employees.index'));

        $this->assertDatabaseMissing('faces', ['employee_id' => $employee->id]);

        $this->actingAs($admin)
            ->delete(route('admin.employees.destroy', $employee))
            ->assertRedirect(route('admin.employees.index'));

        $this->assertDatabaseMissing('employees', ['id' => $employee->id]);
    }

    public function test_employee_show_lists_devices_and_can_remove_from_device(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

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
        ]);

        $employee->devices()->attach($device->id, ['synced_at' => now()]);

        $this->actingAs($admin)
            ->get(route('admin.employees.show', $employee))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Employees/Show')
                ->has('employee.devices', 1));

        $this->actingAs($admin)
            ->post(route('admin.employees.remove-devices', $employee), [
                'device_ids' => [$device->id],
            ])
            ->assertRedirect(route('admin.employees.show', $employee))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('device_employee', [
            'device_id' => $device->id,
            'employee_id' => $employee->id,
        ]);
    }

    public function test_unauthorized_user_cannot_access_employees(): void
    {
        $user = $this->createUserWithPermissions([
            config('menu_permissions.home'),
        ]);

        $this->actingAs($user)
            ->get(route('admin.employees.index'))
            ->assertForbidden();
    }
}
