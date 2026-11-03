<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use App\Models\Device;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * `user` roli standart holatda: ish bo‘limlarida to‘liq huquq, qurilmalarni esa faqat ko‘radi.
 */
class DefaultUserRoleTest extends TestCase
{
    use RefreshDatabase;

    private function regularUser(): User
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::factory()->create(['department_id' => Department::factory()->create()->id]);
        $user->assignRole('user');

        return $user;
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function allowedPages(): array
    {
        return [
            'xodimlar' => ['admin.employees.index'],
            'xodim qo‘shish' => ['admin.employees.create'],
            'izohlar' => ['admin.notes.index'],
            'izoh qo‘shish' => ['admin.notes.create'],
            'hisobotlar' => ['admin.reports.index'],
            'event tortish' => ['admin.reports.pull'],
            'sinxronizatsiya' => ['admin.sync.index'],
            'davomat' => ['admin.attendance.index'],
            'guruhlar' => ['admin.groups.index'],
            'jadvallar' => ['admin.schedules.index'],
            'qurilmalar' => ['admin.devices.index'],
        ];
    }

    #[DataProvider('allowedPages')]
    public function test_allowed_pages_open(string $routeName): void
    {
        $this->actingAs($this->regularUser())
            ->get(route($routeName))
            ->assertOk();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function forbiddenPages(): array
    {
        return [
            'qurilma qo‘shish' => ['admin.devices.create'],
            'kategoriyalar' => ['admin.categories.index'],
            'bo‘limlar' => ['admin.departments.index'],
            'foydalanuvchilar' => ['admin.users.index'],
            'rollar' => ['admin.roles.index'],
            'ruxsatlar' => ['admin.permissions.index'],
            'xodim tortish' => ['admin.employees.pull'],
        ];
    }

    #[DataProvider('forbiddenPages')]
    public function test_other_pages_are_forbidden(string $routeName): void
    {
        $this->actingAs($this->regularUser())
            ->get(route($routeName))
            ->assertForbidden();
    }

    public function test_full_access_resources_can_be_created_edited_and_deleted(): void
    {
        $user = $this->regularUser();

        foreach (['employees', 'groups', 'notes', 'schedules'] as $resource) {
            foreach (['create', 'edit', 'delete'] as $action) {
                $this->assertTrue(
                    $user->can(config("menu_permissions.actions.{$resource}.{$action}")),
                    "{$resource}.{$action} yo‘q",
                );
            }
        }
    }

    public function test_devices_are_view_only(): void
    {
        Http::fake();

        $user = $this->regularUser();
        $device = Device::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('admin.devices.show', $device))->assertOk();

        foreach (['connect', 'disconnect', 'open', 'restart', 'clear', 'sync-employees', 'pull-employees'] as $action) {
            $this->actingAs($user)
                ->post(route("admin.devices.{$action}", $device))
                ->assertForbidden();
        }

        $this->actingAs($user)->get(route('admin.devices.edit', $device))->assertForbidden();
        $this->actingAs($user)->delete(route('admin.devices.destroy', $device))->assertForbidden();

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), (string) $device->ip_address));
        $this->assertModelExists($device);
    }

    public function test_migration_grants_defaults_to_existing_role_without_removing_extras(): void
    {
        $extra = config('menu_permissions.resources.categories');
        $role = Role::findOrCreate('user', 'web');
        $role->syncPermissions([
            Permission::findOrCreate(config('menu_permissions.home'), 'web'),
            Permission::findOrCreate($extra, 'web'),
        ]);

        (require database_path('migrations/2026_11_02_120000_grant_default_user_role_permissions.php'))->up();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $names = $role->fresh()->permissions->pluck('name')->sort()->values()->all();

        $this->seed(DatabaseSeeder::class);
        $seeded = Role::findByName('user', 'web')->permissions->pluck('name')->all();

        $this->assertEqualsCanonicalizing([...$seeded, $extra], $names);
    }
}
