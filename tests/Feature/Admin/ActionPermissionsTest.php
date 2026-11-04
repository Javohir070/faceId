<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Group;
use App\Models\Note;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

/**
 * Qo‘shish, tahrirlash va o‘chirish — alohida ruxsatlar: ruxsat bo‘lmasa tugma chiqmaydi va server rad etadi.
 */
class ActionPermissionsTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Qurilmaga hech qanday so‘rov ketmasin.
        Http::fake();
        Queue::fake();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function resources(): array
    {
        return [
            'kategoriya' => ['categories', 'category'],
            'qurilma' => ['devices', 'device'],
            'bo‘lim' => ['departments', 'department'],
            'guruh' => ['groups', 'group'],
            'jadval' => ['schedules', 'schedule'],
            'xodim' => ['employees', 'employee'],
            'izoh' => ['notes', 'note'],
            'foydalanuvchi' => ['users', 'user'],
            'rol' => ['roles', 'role'],
            'ruxsat' => ['permissions', 'permission'],
        ];
    }

    private function makeRecord(string $resource): Model
    {
        return match ($resource) {
            'categories' => Category::factory()->create(),
            'devices' => Device::factory()->create(),
            'departments' => Department::factory()->create(),
            'groups' => Group::factory()->create(),
            'schedules' => Schedule::factory()->create(),
            'employees' => Employee::factory()->create(),
            'notes' => Note::factory()->create(),
            'users' => User::factory()->create(),
            'roles' => Role::findOrCreate('Sinov roli', 'web'),
            'permissions' => Permission::findOrCreate('Sinov ruxsati', 'web'),
        };
    }

    /**
     * Faqat ko‘rsatilgan ruxsatlarga ega foydalanuvchi (test yordamchisi amal ruxsatlarini ham qo‘shadi — olib tashlaymiz).
     *
     * @param  list<'create'|'edit'|'delete'>  $actions
     */
    private function userWith(string $resource, array $actions = []): User
    {
        $user = $this->createUserWithPermissions([config("menu_permissions.resources.{$resource}")]);

        foreach (['create', 'edit', 'delete'] as $action) {
            if (! in_array($action, $actions, true)) {
                $user->revokePermissionTo(config("menu_permissions.actions.{$resource}.{$action}"));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    #[DataProvider('resources')]
    public function test_view_permission_alone_cannot_create_edit_or_delete(string $resource, string $parameter): void
    {
        $record = $this->makeRecord($resource);
        $user = $this->userWith($resource);
        $params = [$parameter => $record];

        $this->actingAs($user)->get(route("admin.{$resource}.index"))->assertOk();
        $this->actingAs($user)->get(route("admin.{$resource}.create"))->assertForbidden();
        $this->actingAs($user)->post(route("admin.{$resource}.store"), [])->assertForbidden();
        $this->actingAs($user)->get(route("admin.{$resource}.edit", $params))->assertForbidden();
        $this->actingAs($user)->put(route("admin.{$resource}.update", $params), [])->assertForbidden();
        $this->actingAs($user)->delete(route("admin.{$resource}.destroy", $params))->assertForbidden();

        $this->assertModelExists($record);
    }

    #[DataProvider('resources')]
    public function test_create_permission_opens_create_only(string $resource, string $parameter): void
    {
        $record = $this->makeRecord($resource);
        $user = $this->userWith($resource, ['create']);
        $params = [$parameter => $record];

        $response = $this->actingAs($user)->get(route("admin.{$resource}.create"));
        $this->assertContains($response->getStatusCode(), [200, 302]);

        $this->actingAs($user)->get(route("admin.{$resource}.edit", $params))->assertForbidden();
        $this->actingAs($user)->delete(route("admin.{$resource}.destroy", $params))->assertForbidden();
    }

    #[DataProvider('resources')]
    public function test_edit_permission_opens_edit_but_not_delete(string $resource, string $parameter): void
    {
        $record = $this->makeRecord($resource);
        $user = $this->userWith($resource, ['edit']);
        $params = [$parameter => $record];

        $response = $this->actingAs($user)->get(route("admin.{$resource}.edit", $params));
        $this->assertContains($response->getStatusCode(), [200, 302]);

        $this->actingAs($user)->delete(route("admin.{$resource}.destroy", $params))->assertForbidden();
        $this->assertModelExists($record);
    }

    #[DataProvider('resources')]
    public function test_delete_permission_deletes_but_cannot_edit(string $resource, string $parameter): void
    {
        $record = $this->makeRecord($resource);
        $user = $this->userWith($resource, ['delete']);
        $params = [$parameter => $record];

        $this->actingAs($user)->get(route("admin.{$resource}.edit", $params))->assertForbidden();
        $this->actingAs($user)->delete(route("admin.{$resource}.destroy", $params))->assertRedirect();

        $this->assertModelMissing($record);
    }

    public function test_shared_can_map_has_action_flags(): void
    {
        $user = $this->userWith('employees', ['edit']);

        $this->actingAs($user)
            ->get(route('admin.employees.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can', fn ($can) => $can['employees'] === true
                    && $can['employees.create'] === false
                    && $can['employees.edit'] === true
                    && $can['employees.delete'] === false
                    && $can['devices.edit'] === false));
    }

    public function test_bulk_delete_and_device_removal_need_delete_permission(): void
    {
        $employee = Employee::factory()->create();
        $group = Group::factory()->create();
        $device = Device::factory()->create();

        $employees = $this->userWith('employees', ['edit']);
        $this->actingAs($employees)
            ->post(route('admin.employees.bulk-destroy'), ['employee_ids' => [$employee->id]])
            ->assertForbidden();
        $this->actingAs($employees)
            ->post(route('admin.employees.remove-devices', $employee), ['device_ids' => [$device->id]])
            ->assertForbidden();

        $this->actingAs($this->userWith('groups', ['edit']))
            ->post(route('admin.groups.bulk-destroy'), ['group_ids' => [$group->id]])
            ->assertForbidden();

        $devices = $this->userWith('devices', ['edit']);
        $this->actingAs($devices)->post(route('admin.devices.clear', $device))->assertForbidden();
        $this->actingAs($devices)
            ->post(route('admin.devices.remove-employees', $device), ['employee_ids' => [$employee->id]])
            ->assertForbidden();

        $this->assertModelExists($employee);
        $this->assertModelExists($group);
        Http::assertNothingSent();
    }

    public function test_only_device_ping_stays_under_view_permission(): void
    {
        $device = Device::factory()->create();
        $user = $this->userWith('devices');

        $this->actingAs($user)
            ->post(route('admin.devices.ping', $device))
            ->assertRedirect();

        foreach (['connect', 'disconnect', 'open', 'restart', 'sync-employees'] as $action) {
            $this->actingAs($user)
                ->post(route("admin.devices.{$action}", $device))
                ->assertForbidden();
        }
    }

    public function test_linking_devices_to_user_needs_user_edit_permission(): void
    {
        $target = User::factory()->create();

        $this->actingAs($this->userWith('users'))
            ->put(route('admin.users.devices.update', $target), ['device_ids' => []])
            ->assertForbidden();

        $this->actingAs($this->userWith('users', ['edit']))
            ->put(route('admin.users.devices.update', $target), ['device_ids' => []])
            ->assertRedirect();
    }

    public function test_attendance_note_needs_note_create_permission(): void
    {
        $employee = Employee::factory()->create();
        $user = $this->createUserWithPermissions([
            config('menu_permissions.resources.attendance'),
            config('menu_permissions.resources.notes'),
        ]);
        $user->revokePermissionTo(config('menu_permissions.actions.notes.create'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user->fresh())
            ->post(route('admin.attendance.notes.store'), ['employee_id' => $employee->id, 'type' => 3, 'dt' => '2026-11-02'])
            ->assertForbidden();
    }

    public function test_edit_drawer_link_does_not_open_without_edit_permission(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->userWith('categories'))
            ->get(route('admin.categories.index', ['create' => 1]))
            ->assertInertia(fn (Assert $page) => $page->where('drawer', null));

        $this->actingAs($this->userWith('categories', ['create']))
            ->get(route('admin.categories.index', ['create' => 1]))
            ->assertInertia(fn (Assert $page) => $page->where('drawer.mode', 'create'));

        $this->actingAs($this->userWith('categories'))
            ->get(route('admin.categories.index', ['edit' => $category->id]))
            ->assertInertia(fn (Assert $page) => $page->where('drawer', null));

        $this->actingAs($this->userWith('categories', ['edit']))
            ->get(route('admin.categories.index', ['edit' => $category->id]))
            ->assertInertia(fn (Assert $page) => $page->where('drawer.record.id', $category->id));
    }

    public function test_migration_gives_new_permissions_to_everyone_who_had_the_view_permission(): void
    {
        $migration = require database_path('migrations/2026_11_02_100000_add_action_permissions.php');
        $migration->down();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $view = Permission::findOrCreate('Xodimlar', 'web');
        $manager = Role::findOrCreate('Menejer', 'web');
        $manager->givePermissionTo($view);
        $viewer = Role::findOrCreate('Kuzatuvchi', 'web');
        $direct = User::factory()->create();
        $direct->givePermissionTo($view);
        $superAdmin = Role::findOrCreate('super-admin', 'web');

        $migration->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($manager->fresh()->hasPermissionTo('Xodimlarni qo‘shish'));
        $this->assertTrue($manager->fresh()->hasPermissionTo('Xodimlarni tahrirlash'));
        $this->assertTrue($manager->fresh()->hasPermissionTo('Xodimlarni o‘chirish'));
        $this->assertFalse($manager->fresh()->hasPermissionTo('Qurilmalarni o‘chirish'));
        $this->assertFalse($viewer->fresh()->hasPermissionTo('Xodimlarni o‘chirish'));
        $this->assertTrue($direct->fresh()->hasPermissionTo('Xodimlarni o‘chirish'));
        $this->assertTrue($superAdmin->fresh()->hasPermissionTo('Qurilmalarni o‘chirish'));
    }

    public function test_migration_covers_every_action_permission_in_config(): void
    {
        $migration = require database_path('migrations/2026_11_02_100000_add_action_permissions.php');
        $migration->down();
        $migration->up();

        foreach (config('menu_permissions.actions') as $key => $actions) {
            $this->assertArrayHasKey($key, config('menu_permissions.resources'));

            foreach ($actions as $name) {
                $this->assertDatabaseHas('permissions', ['name' => $name, 'guard_name' => 'web']);
            }
        }
    }

    public function test_role_form_shows_permissions_as_a_matrix(): void
    {
        foreach (['Xodimlar', 'Foydalanuvchilar', 'Hisobotlar', 'Event tortish'] as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $admin = $this->createUserWithPermissions([config('menu_permissions.resources')['roles']]);

        $this->actingAs($admin)
            ->get(route('admin.roles.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('permissions', function ($groups) {
                    $main = collect($groups['Asosiy'])->keyBy('name');
                    $settings = collect($groups['Sozlamalar'])->keyBy('name');

                    return $main['Xodimlar']['create'] === 'Xodimlarni qo‘shish'
                        && $main['Xodimlar']['edit'] === 'Xodimlarni tahrirlash'
                        && $main['Xodimlar']['delete'] === 'Xodimlarni o‘chirish'
                        && $main['Hisobotlar']['edit'] === null
                        // Event tortish menyuda alohida: Sozlamalar ichida emas.
                        && $main->has('Event tortish')
                        && ! $settings->has('Event tortish')
                        // Foydalanuvchilar faqat bir guruhda; amal ruxsatlari alohida qator emas.
                        && ! $main->has('Foydalanuvchilar')
                        && $settings['Foydalanuvchilar']['delete'] === 'Foydalanuvchilarni o‘chirish'
                        && ! collect($groups)->flatten(1)->pluck('name')->contains('Xodimlarni tahrirlash');
                }));
    }
}
