<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class UnitPageTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-11 12:00:00'));
    }

    public function test_page_requires_units_permission(): void
    {
        $this->assertTrue(Permission::query()->where('name', "Tarkibiy bo'linmalar")->exists());

        $user = $this->createUserWithPermissions([config('menu_permissions.resources')['reports']]);

        $this->actingAs($user)->get(route('admin.units.index'))->assertForbidden();
    }

    public function test_page_renders_board(): void
    {
        $employee = $this->employee(Department::factory()->create());

        $this->actingAs($this->createUserWithPermissions([$this->unitsPermission()]))
            ->get(route('admin.units.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Units/Index')
                ->where('board.counts.total', 1)
                ->where('board.units.0.employees.0.name', $employee->name));
    }

    public function test_department_user_sees_only_own_department(): void
    {
        $own = Department::factory()->create(['name' => 'Mening Bolimim']);
        $other = Department::factory()->create(['name' => 'Begona Bolim']);
        $this->employee($own, ['name' => 'Mening Xodimim']);
        $this->employee($other, ['name' => 'Begona Xodim']);

        $user = $this->createDepartmentUserWithPermissions($own->id, [$this->unitsPermission()]);

        $this->actingAs($user)
            ->get(route('admin.units.index'))
            ->assertOk()
            ->assertDontSee('Begona Xodim', false)
            ->assertDontSee('Begona Bolim', false)
            ->assertInertia(fn (Assert $page) => $page
                ->where('board.counts.total', 1)
                ->has('board.units', 1)
                ->where('board.units.0.department', 'Mening Bolimim')
                ->where('board.units.0.employees.0.name', 'Mening Xodimim'));
    }

    public function test_page_filters_by_department(): void
    {
        $agency = Department::factory()->create(['name' => 'Agentlik']);
        $bureau = Department::factory()->create(['name' => 'Byuro']);
        $this->employee($agency, ['name' => 'Agentlik Xodimi']);
        $this->employee($bureau, ['name' => 'Byuro Xodimi 1']);
        $this->employee($bureau, ['name' => 'Byuro Xodimi 2']);

        $this->actingAs($this->createUserWithPermissions([$this->unitsPermission()]))
            ->get(route('admin.units.index', ['department_id' => $bureau->id]))
            ->assertOk()
            ->assertDontSee('Agentlik Xodimi', false)
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.department_id', $bureau->id)
                ->where('departmentLocked', false)
                ->where('departments', [
                    ['id' => $agency->id, 'name' => 'Agentlik'],
                    ['id' => $bureau->id, 'name' => 'Byuro'],
                ])
                ->where('board.counts.total', 2)
                ->where('board.counts.unexcused', 2)
                ->has('board.units', 1)
                ->where('board.units.0.department', 'Byuro'));
    }

    public function test_page_without_filter_shows_all_departments(): void
    {
        $this->employee(Department::factory()->create());
        $this->employee(Department::factory()->create());

        $this->actingAs($this->createUserWithPermissions([$this->unitsPermission()]))
            ->get(route('admin.units.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.department_id', null)
                ->where('board.counts.total', 2)
                ->has('board.units', 2));
    }

    public function test_department_user_cannot_filter_into_other_department(): void
    {
        $own = Department::factory()->create(['name' => 'Mening Bolimim']);
        $other = Department::factory()->create(['name' => 'Begona Bolim']);
        $this->employee($own, ['name' => 'Mening Xodimim']);
        $this->employee($other, ['name' => 'Begona Xodim']);

        $user = $this->createDepartmentUserWithPermissions($own->id, [$this->unitsPermission()]);

        $this->actingAs($user)
            ->get(route('admin.units.index', ['department_id' => $other->id]))
            ->assertOk()
            ->assertDontSee('Begona Xodim', false)
            ->assertDontSee('Begona Bolim', false)
            ->assertInertia(fn (Assert $page) => $page
                ->where('departmentLocked', true)
                ->where('departments', [['id' => $own->id, 'name' => 'Mening Bolimim']])
                ->where('board.counts.total', 0)
                ->where('board.units', []));
    }

    public function test_invalid_department_filter_is_rejected(): void
    {
        $this->actingAs($this->createUserWithPermissions([$this->unitsPermission()]))
            ->from(route('admin.units.index'))
            ->get(route('admin.units.index', ['department_id' => 'abc']))
            ->assertRedirect(route('admin.units.index'))
            ->assertSessionHasErrors('department_id');
    }

    public function test_user_without_department_sees_empty_board(): void
    {
        $this->employee(Department::factory()->create());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user = User::factory()->create(['department_id' => null]);
        $user->givePermissionTo($this->unitsPermission());
        $user->assignRole(Role::findOrCreate('user', 'web'));

        $this->actingAs($user)
            ->get(route('admin.units.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('board.counts.total', 0)
                ->where('board.units', []));
    }

    public function test_face_url_hidden_without_employees_permission(): void
    {
        $employee = $this->employee(Department::factory()->create());
        $employee->face()->create(['face' => base64_encode('rasm')]);

        $this->actingAs($this->createUserWithPermissions([$this->unitsPermission()]))
            ->get(route('admin.units.index'))
            ->assertInertia(fn (Assert $page) => $page->where('board.units.0.employees.0.face_url', null));

        $this->actingAs($this->createUserWithPermissions([
            $this->unitsPermission(),
            config('menu_permissions.resources')['employees'],
        ]))
            ->get(route('admin.units.index'))
            ->assertInertia(fn (Assert $page) => $page->where(
                'board.units.0.employees.0.face_url',
                fn (?string $url): bool => $url !== null && str_contains($url, '/face?v='),
            ));
    }

    public function test_card_is_never_sent(): void
    {
        $this->employee(Department::factory()->create(), ['card' => '12345678901234']);

        $this->actingAs($this->createUserWithPermissions([$this->unitsPermission()]))
            ->get(route('admin.units.index'))
            ->assertOk()
            ->assertDontSee('12345678901234', false);
    }

    private function unitsPermission(): string
    {
        return config('menu_permissions.resources')['units'];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function employee(Department $department, array $attributes = []): Employee
    {
        return Employee::factory()->create(array_merge([
            'department_id' => $department->id,
            'schedule_id' => Schedule::factory()->create(['enter_time' => '09:00', 'exit_time' => '18:00'])->id,
            'valid_from_dt' => '2026-01-01 00:00:00',
            'valid_to_dt' => '2027-12-31 00:00:00',
        ], $attributes));
    }
}
