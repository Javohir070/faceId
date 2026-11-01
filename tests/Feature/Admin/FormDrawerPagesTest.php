<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Department;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

/**
 * Kichik ma’lumotnomalar (kategoriya, bo‘lim, jadval, ruxsat) alohida forma/ko‘rish sahifasisiz:
 * qo‘shish va tahrirlash ro‘yxatdagi o‘ng panelda.
 */
class FormDrawerPagesTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function resources(): array
    {
        return [
            'kategoriyalar' => ['categories', 'category', 'Admin/Categories/Index'],
            'bo‘limlar' => ['departments', 'department', 'Admin/Departments/Index'],
            'jadvallar' => ['schedules', 'schedule', 'Admin/Schedules/Index'],
            'ruxsatlar' => ['permissions', 'permission', 'Admin/Permissions/Index'],
        ];
    }

    private function makeRecord(string $resource): Model
    {
        return match ($resource) {
            'categories' => Category::factory()->create(['name' => 'Sinov nomi']),
            'departments' => Department::factory()->create(['name' => 'Sinov nomi']),
            'schedules' => Schedule::factory()->create(['name' => 'Sinov nomi', 'enter_time' => '09:00', 'exit_time' => '18:00']),
            'permissions' => Permission::findOrCreate('Sinov nomi', 'web'),
        };
    }

    #[DataProvider('resources')]
    public function test_create_edit_and_show_links_open_the_drawer_on_the_list(string $resource, string $parameter, string $component): void
    {
        $admin = $this->createUserWithPermissions();
        $record = $this->makeRecord($resource);

        $this->actingAs($admin)
            ->get(route("admin.{$resource}.create"))
            ->assertRedirect(route("admin.{$resource}.index", ['create' => 1]));

        $this->actingAs($admin)
            ->get(route("admin.{$resource}.edit", [$parameter => $record]))
            ->assertRedirect(route("admin.{$resource}.index", ['edit' => $record->getKey()]));

        $this->actingAs($admin)
            ->get(route("admin.{$resource}.show", [$parameter => $record]))
            ->assertRedirect(route("admin.{$resource}.index"));

        $this->actingAs($admin)
            ->get(route("admin.{$resource}.index", ['create' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->component($component)
                ->where('drawer.mode', 'create'));

        $this->actingAs($admin)
            ->get(route("admin.{$resource}.index", ['edit' => $record->getKey()]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('drawer.mode', 'edit')
                ->where('drawer.record.id', $record->getKey())
                ->where('drawer.record.name', 'Sinov nomi'));

        $this->actingAs($admin)
            ->get(route("admin.{$resource}.index", ['edit' => 999999]))
            ->assertInertia(fn (Assert $page) => $page->where('drawer', null));
    }

    public function test_schedule_drawer_record_has_times(): void
    {
        $admin = $this->createUserWithPermissions();
        $schedule = $this->makeRecord('schedules');

        $this->actingAs($admin)
            ->get(route('admin.schedules.index', ['edit' => $schedule->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('drawer.record.enter_time', '09:00')
                ->where('drawer.record.exit_time', '18:00'));
    }

    public function test_validation_messages_are_in_uzbek(): void
    {
        $admin = $this->createUserWithPermissions();

        $this->actingAs($admin)
            ->post(route('admin.departments.store'), ['name' => ''])
            ->assertSessionHasErrors(['name' => 'Bo‘lim nomini kiriting.']);

        $this->actingAs($admin)
            ->post(route('admin.permissions.store'), ['name' => ''])
            ->assertSessionHasErrors(['name' => 'Ruxsat nomini kiriting.']);

        $this->actingAs($admin)
            ->post(route('admin.schedules.store'), ['name' => '', 'enter_time' => '', 'exit_time' => ''])
            ->assertSessionHasErrors([
                'name' => 'Jadval nomini kiriting.',
                'enter_time' => 'Kirish vaqtini kiriting.',
                'exit_time' => 'Chiqish vaqtini kiriting.',
            ]);
    }
}
