<?php

namespace Tests\Feature\Admin;

use App\Enums\NoteType;
use App\Models\Category;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Face;
use App\Models\Group;
use App\Models\Note;
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
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Employees/Index')
                ->has('filters')
                ->has('departments')
                ->has('schedules')
                ->has('devices'));

        $this->actingAs($admin)
            ->get(route('admin.employees.index', [
                'search' => 'Ali',
                'department_id' => $department->id,
                'schedule_id' => $schedule->id,
                'has_face' => '1',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Employees/Index')
                ->where('filters.search', 'Ali')
                ->where('filters.department_id', $department->id)
                ->where('filters.schedule_id', $schedule->id)
                ->where('filters.has_face', '1')
                ->has('employees.data', 0));

        $this->actingAs($admin)
            ->post(route('admin.employees.store'), [
                'name' => 'Ali Valiyev',
                'card' => '12345678',
                'gender' => 1,
                'position' => 'Operator',
                'department_id' => $department->id,
                'group_id' => null,
                'schedule_id' => $schedule->id,
                'valid_from_dt' => '2026-01-01T09:00',
                'valid_to_dt' => '2026-12-31T18:00',
                'face' => $face,
            ])
            ->assertRedirect(route('admin.employees.index'));

        $employee = Employee::query()->where('card', '12345678')->first();
        $this->assertNotNull($employee);
        $this->assertSame('Ali Valiyev', $employee->name);
        $this->assertSame(1, $employee->gender);
        $this->assertSame('Operator', $employee->position);
        $this->assertSame($department->id, $employee->department_id);
        $this->assertNull($employee->group_id);
        $this->assertSame($schedule->id, $employee->schedule_id);
        $this->assertDatabaseHas('faces', ['employee_id' => $employee->id]);
        $this->assertNotEmpty($employee->face?->face);

        $replacementFace = UploadedFile::fake()->image('face-2.jpg', 180, 180);

        $this->actingAs($admin)
            ->put(route('admin.employees.update', $employee), [
                'name' => 'Ali Valiyev Updated',
                'card' => '12345678',
                'gender' => 2,
                'position' => 'Nazoratchi',
                'department_id' => $department->id,
                'schedule_id' => $schedule->id,
                'valid_from_dt' => '2026-02-01T09:00',
                'valid_to_dt' => '2027-01-31T18:00',
                'face' => $replacementFace,
            ])
            ->assertRedirect(route('admin.employees.index'));

        $employee->refresh();
        $this->assertSame('Ali Valiyev Updated', $employee->name);
        $this->assertSame(2, $employee->gender);
        $this->assertSame('Nazoratchi', $employee->position);
        $this->assertSame(1, Face::query()->where('employee_id', $employee->id)->count());

        $this->actingAs($admin)
            ->put(route('admin.employees.update', $employee), [
                'name' => 'Ali Valiyev Updated',
                'card' => '12345678',
                'gender' => 2,
                'position' => 'Nazoratchi',
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
            ->from(route('admin.employees.show', $employee))
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

    public function test_employee_edit_includes_linked_devices(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['employees'],
        ]);

        $device = Device::factory()->create([
            'user_id' => $admin->id,
            'category_id' => Category::factory(),
        ]);
        $employee = Employee::factory()->create();
        $employee->devices()->attach($device->id, ['synced_at' => now()]);

        $this->actingAs($admin)
            ->get(route('admin.employees.edit', $employee))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Employees/Form')
                ->has('employee.devices', 1)
                ->where('employee.devices.0.id', $device->id)
                ->where('employee.devices.0.name', $device->name));
    }

    public function test_cannot_change_card_while_linked_to_devices(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['employees'],
        ]);

        $device = Device::factory()->create([
            'user_id' => $admin->id,
            'category_id' => Category::factory(),
        ]);
        $employee = Employee::factory()->create([
            'card' => '11111111',
            'department_id' => Department::factory(),
            'schedule_id' => Schedule::factory(),
        ]);
        $employee->devices()->attach($device->id, ['synced_at' => now()]);

        $this->actingAs($admin)
            ->put(route('admin.employees.update', $employee), [
                'name' => $employee->name,
                'card' => '22222222',
                'gender' => $employee->gender,
                'position' => $employee->position,
                'department_id' => $employee->department_id,
                'schedule_id' => $employee->schedule_id,
                'valid_from_dt' => $employee->valid_from_dt->format('Y-m-d\TH:i'),
                'valid_to_dt' => $employee->valid_to_dt->format('Y-m-d\TH:i'),
            ])
            ->assertSessionHasErrors('card');

        $this->assertSame('11111111', $employee->fresh()->card);
    }

    public function test_can_change_card_when_not_linked_to_devices(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['employees'],
        ]);

        $employee = Employee::factory()->create([
            'card' => '11111111',
            'department_id' => Department::factory(),
            'schedule_id' => Schedule::factory(),
        ]);

        $this->actingAs($admin)
            ->put(route('admin.employees.update', $employee), [
                'name' => $employee->name,
                'card' => '22222222',
                'gender' => $employee->gender,
                'position' => $employee->position,
                'department_id' => $employee->department_id,
                'schedule_id' => $employee->schedule_id,
                'valid_from_dt' => $employee->valid_from_dt->format('Y-m-d\TH:i'),
                'valid_to_dt' => $employee->valid_to_dt->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect(route('admin.employees.index'));

        $this->assertSame('22222222', $employee->fresh()->card);
    }

    public function test_remove_devices_from_edit_redirects_back_to_edit(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['employees'],
        ]);

        $device = Device::factory()->create([
            'user_id' => $admin->id,
            'category_id' => Category::factory(),
        ]);
        $employee = Employee::factory()->create();
        $employee->devices()->attach($device->id, ['synced_at' => now()]);

        $this->actingAs($admin)
            ->from(route('admin.employees.edit', $employee))
            ->post(route('admin.employees.remove-devices', $employee), [
                'device_ids' => [$device->id],
            ])
            ->assertRedirect(route('admin.employees.edit', $employee))
            ->assertSessionHas('success');
    }

    public function test_employee_show_lists_notes_newest_first_with_type_labels(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['employees'],
        ]);
        $employee = Employee::factory()->create();

        Note::factory()->create([
            'employee_id' => $employee->id,
            'type' => NoteType::CameLate,
            'dt' => '2026-08-01',
            'note' => 'Birinchi izoh.',
        ]);
        Note::factory()->create([
            'employee_id' => $employee->id,
            'type' => NoteType::Absent,
            'dt' => '2026-08-03',
            'note' => 'Eng yangi izoh.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.employees.show', $employee))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Employees/Show')
                ->has('notes', 2)
                ->where('notes.0.type', NoteType::Absent->value)
                ->where('notes.0.type_label', NoteType::Absent->label())
                ->where('notes.0.dt', '2026-08-03')
                ->where('notes.0.note', 'Eng yangi izoh.')
                ->where('notes.1.type', NoteType::CameLate->value));
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

    public function test_employee_index_filters_by_department_and_face(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['employees'],
        ]);

        $it = Department::factory()->create(['name' => 'IT']);
        $hr = Department::factory()->create(['name' => 'HR']);
        $schedule = Schedule::factory()->create();

        $withFace = Employee::factory()->create([
            'name' => 'Face User',
            'card' => '11112222',
            'department_id' => $it->id,
            'schedule_id' => $schedule->id,
        ]);
        $withFace->face()->create(['face' => base64_encode('jpg')]);

        Employee::factory()->create([
            'name' => 'No Face',
            'card' => '33334444',
            'department_id' => $hr->id,
            'schedule_id' => $schedule->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.employees.index', [
                'department_id' => $it->id,
                'has_face' => '1',
                'search' => 'Face',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Employees/Index')
                ->has('employees.data', 1)
                ->where('employees.data.0.card', '11112222'));
    }

    public function test_employee_can_be_saved_with_group_from_same_department(): void
    {
        Storage::fake('local');

        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['employees'],
        ]);

        $department = Department::factory()->create();
        $otherDepartment = Department::factory()->create();
        $schedule = Schedule::factory()->create();
        $group = Group::factory()->create(['department_id' => $department->id]);
        $foreignGroup = Group::factory()->create(['department_id' => $otherDepartment->id]);
        $face = UploadedFile::fake()->image('face.jpg', 200, 200);

        $this->actingAs($admin)
            ->post(route('admin.employees.store'), [
                'name' => 'Grouped User',
                'card' => '55556666',
                'gender' => 1,
                'department_id' => $department->id,
                'group_id' => $foreignGroup->id,
                'schedule_id' => $schedule->id,
                'valid_from_dt' => '2026-01-01T09:00',
                'valid_to_dt' => '2026-12-31T18:00',
                'face' => $face,
            ])
            ->assertSessionHasErrors('group_id');

        $this->actingAs($admin)
            ->post(route('admin.employees.store'), [
                'name' => 'Grouped User',
                'card' => '55556666',
                'gender' => 1,
                'department_id' => $department->id,
                'group_id' => $group->id,
                'schedule_id' => $schedule->id,
                'valid_from_dt' => '2026-01-01T09:00',
                'valid_to_dt' => '2026-12-31T18:00',
                'face' => $face,
            ])
            ->assertRedirect(route('admin.employees.index'));

        $this->assertDatabaseHas('employees', [
            'card' => '55556666',
            'group_id' => $group->id,
        ]);
    }
}
