<?php

namespace Tests\Feature\Admin;

use App\Jobs\Hikvision\RemoveHikvisionUsersJob;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class DeviceUserAccessTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    private function scopedUser(): User
    {
        return $this->createDepartmentUserWithPermissions(Department::factory()->create()->id);
    }

    public function test_scoped_user_sees_only_own_and_linked_devices(): void
    {
        $user = $this->scopedUser();
        $own = Device::factory()->create(['user_id' => $user->id, 'name' => 'O‘zim qo‘shgan']);
        $linked = Device::factory()->create(['name' => 'Bog‘langan']);
        $foreign = Device::factory()->create(['name' => 'Begona']);
        $linked->users()->attach($user->id);

        $this->actingAs($user)
            ->get(route('admin.devices.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('devices.data', 2)
                ->where('devices.data', fn ($devices) => collect($devices)->pluck('id')->sort()->values()->all()
                    === collect([$own->id, $linked->id])->sort()->values()->all()));

        $this->actingAs($user)->get(route('admin.devices.show', $linked))->assertOk();
        $this->actingAs($user)->get(route('admin.devices.show', $foreign))->assertNotFound();
        $this->actingAs($user)->post(route('admin.devices.ping', $foreign))->assertNotFound();
    }

    public function test_super_admin_and_background_jobs_see_all_devices(): void
    {
        Device::factory()->count(3)->create();

        $this->assertSame(3, Device::query()->count());

        $this->actingAs($this->createUserWithPermissions([config('menu_permissions.resources')['devices']]))
            ->get(route('admin.devices.index'))
            ->assertInertia(fn (Assert $page) => $page->has('devices.data', 3));
    }

    public function test_admin_links_devices_to_user_from_users_list(): void
    {
        $admin = $this->createUserWithPermissions([config('menu_permissions.resources')['users']]);
        $user = $this->scopedUser();
        [$first, $second, $third] = Device::factory()->count(3)->create()->all();
        $third->users()->attach($user->id);

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->put(route('admin.users.devices.update', $user), ['device_ids' => [$first->id, $second->id]])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $user->assignedDevices()->withoutGlobalScopes()->pluck('devices.id')->all(),
        );

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('devices', 3)
                ->where('users.data', fn ($users) => collect($users)->firstWhere('id', $user->id)['device_ids'] !== null
                    && count(collect($users)->firstWhere('id', $user->id)['device_ids']) === 2));
    }

    public function test_scoped_editor_cannot_link_hidden_devices_and_keeps_their_links(): void
    {
        $editor = $this->scopedUser();
        $target = $this->scopedUser();
        $visible = Device::factory()->create(['user_id' => $editor->id]);
        $hidden = Device::factory()->create();
        $hidden->users()->attach($target->id);

        $this->actingAs($editor)
            ->put(route('admin.users.devices.update', $target), ['device_ids' => [$hidden->id]])
            ->assertSessionHasErrors('device_ids');

        $this->actingAs($editor)
            ->put(route('admin.users.devices.update', $target), ['device_ids' => [$visible->id]])
            ->assertSessionHasNoErrors();

        // Muharrir ko‘rmaydigan qurilma bog‘lanishi o‘chib ketmaydi.
        $this->assertEqualsCanonicalizing(
            [$visible->id, $hidden->id],
            $target->assignedDevices()->withoutGlobalScopes()->pluck('devices.id')->all(),
        );
    }

    public function test_scoped_user_cannot_sync_to_hidden_device(): void
    {
        $department = Department::factory()->create();
        $user = $this->createDepartmentUserWithPermissions($department->id);
        $hidden = Device::factory()->create();
        $employee = Employee::factory()->create(['department_id' => $department->id]);

        $this->actingAs($user)
            ->postJson(route('admin.sync.transfer-one'), ['employee_id' => $employee->id, 'device_id' => $hidden->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('device_id');
    }

    public function test_deleting_employee_removes_it_from_all_synced_devices_even_hidden_ones(): void
    {
        Queue::fake();
        $department = Department::factory()->create();
        $user = $this->createDepartmentUserWithPermissions($department->id);
        $mine = Device::factory()->create(['user_id' => $user->id]);
        $hidden = Device::factory()->create();
        $employee = Employee::factory()->create(['department_id' => $department->id]);
        $employee->devices()->attach([$mine->id => ['synced_at' => now()], $hidden->id => ['synced_at' => now()]]);

        $this->actingAs($user)
            ->delete(route('admin.employees.destroy', $employee))
            ->assertRedirect(route('admin.employees.index'));

        Queue::assertPushed(RemoveHikvisionUsersJob::class, function (RemoveHikvisionUsersJob $job) use ($mine, $hidden): bool {
            $deviceIds = $job->deviceIds;
            sort($deviceIds);

            return $deviceIds === collect([$mine->id, $hidden->id])->sort()->values()->all();
        });
    }
}
