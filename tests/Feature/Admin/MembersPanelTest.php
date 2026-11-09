<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

/**
 * Bo‘lim va guruh xodimlari o‘ng paneli, bo‘lim va qurilmalar ro‘yxatidagi xodimlar soni.
 */
class MembersPanelTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    private Department $nukus;

    private Department $xiva;

    private Group $parent;

    private Group $child;

    protected function setUp(): void
    {
        parent::setUp();

        $this->nukus = Department::factory()->create(['name' => 'Nukus']);
        $this->xiva = Department::factory()->create(['name' => 'Xiva']);
        $this->parent = Group::factory()->create(['name' => 'IT', 'department_id' => $this->nukus->id]);
        $this->child = Group::factory()->create(['name' => 'Dasturchilar', 'department_id' => $this->nukus->id, 'parent_id' => $this->parent->id]);

        $accounting = Group::factory()->create(['name' => 'Buxgalteriya', 'department_id' => $this->nukus->id]);

        $this->employee('Ali', $this->nukus, $this->parent, withFace: true);
        $this->employee('Vali', $this->nukus, $this->child);
        $this->employee('Guruhsiz', $this->nukus);
        $this->employee('Zafar', $this->nukus, $accounting);
        $this->employee('Xivalik', $this->xiva);
    }

    public function test_departments_list_shows_how_many_employees_each_has(): void
    {
        $this->actingAs($this->createUserWithPermissions())
            ->get(route('admin.departments.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('departments.data', fn ($rows): bool => collect($rows)->pluck('employees_count', 'name')->all() === ['Nukus' => 4, 'Xiva' => 1]));
    }

    public function test_department_panel_lists_its_employees(): void
    {
        $this->actingAs($this->createUserWithPermissions())
            ->get(route('admin.departments.index', ['members' => 'department:'.$this->nukus->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('members')
                ->reload(fn (Assert $reload) => $reload
                    ->where('members.title', 'Nukus')
                    ->where('members.total', 4)
                    // Guruhlar daraxt tartibida (nomi bo‘yicha, pastki guruh otasidan keyin), guruhsizlar oxirida.
                    ->where('members.employees', fn ($rows): bool => collect($rows)->map(fn ($row): string => $row['name'].' — '.($row['group'] ?? 'guruhsiz'))->all() === [
                        'Zafar — Buxgalteriya',
                        'Ali — IT',
                        'Vali — IT › Dasturchilar',
                        'Guruhsiz — guruhsiz',
                    ])
                    ->where('members.filter', ['department_ids' => [$this->nukus->id]]), only: 'members'));
    }

    public function test_group_panel_includes_subgroups(): void
    {
        $this->panel('group:'.$this->parent->id, $this->createUserWithPermissions(), fn (Assert $reload) => $reload
            ->where('members.title', 'IT')
            ->where('members.subtitle', 'Nukus')
            ->where('members.total', 2)
            ->where('members.employees', fn ($rows): bool => collect($rows)->pluck('group', 'name')->all() === ['Ali' => 'IT', 'Vali' => 'IT › Dasturchilar']));
    }

    public function test_panel_gives_a_small_photo_link_instead_of_the_photo_itself(): void
    {
        $this->panel('group:'.$this->parent->id, $this->createUserWithPermissions(), fn (Assert $reload) => $reload
            ->where('members.employees.0.name', 'Ali')
            ->where('members.employees.0.face_url', fn (string $url): bool => str_contains($url, '/admin/employees/') && str_contains($url, '/face?v='))
            ->where('members.employees.1.face_url', null));
    }

    public function test_user_cannot_open_another_departments_group_or_department(): void
    {
        $user = $this->createDepartmentUserWithPermissions($this->xiva->id);

        $this->panel('group:'.$this->parent->id, $user, fn (Assert $reload) => $reload->where('members', null));
        $this->panel('department:'.$this->nukus->id, $user, fn (Assert $reload) => $reload->where('members', null));
        $this->panel('department:'.$this->xiva->id, $user, fn (Assert $reload) => $reload->where('members.total', 1));
    }

    public function test_devices_list_shows_linked_and_synced_employee_counts(): void
    {
        $admin = $this->createUserWithPermissions();
        $device = Device::factory()->create(['user_id' => User::factory(), 'category_id' => Category::factory()]);
        $employees = Employee::query()->withoutGlobalScopes()->orderBy('id')->get();
        $device->employees()->attach($employees[0]->id, ['synced_at' => now()]);
        $device->employees()->attach($employees[1]->id, ['synced_at' => now()]);
        $device->employees()->attach($employees[2]->id, ['synced_at' => null]);

        $this->actingAs($admin)
            ->get(route('admin.devices.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('devices.data.0.employees_count', 3)
                ->where('devices.data.0.synced_employees_count', 2));
    }

    /**
     * Guruhlar sahifasida panelni ochish: Inertia faqat "members" ni qayta yuklaydi.
     */
    private function panel(string $key, User $user, \Closure $assert): void
    {
        $this->actingAs($user)
            ->get(route('admin.groups.index', ['members' => $key]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->reload($assert, only: 'members'));
    }

    private function employee(string $name, Department $department, ?Group $group = null, bool $withFace = false): void
    {
        $employee = Employee::factory()->create([
            'name' => $name,
            'department_id' => $department->id,
            'group_id' => $group?->id,
        ]);

        if ($withFace) {
            $employee->face()->create(['face' => base64_encode('rasm')]);
        }
    }
}
