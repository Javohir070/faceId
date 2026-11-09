<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

/**
 * Bir nechta xodimning muddatini o‘ng paneldan bir yo‘la uzaytirish.
 */
class EmployeeBulkExtendTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-10 10:00'));
    }

    public function test_selected_employees_get_the_new_end_date(): void
    {
        $first = $this->expired('Ali');
        $second = $this->expired('Vali');
        $untouched = $this->expired('Tanlanmagan');

        $this->actingAs($this->createUserWithPermissions())
            ->from(route('admin.employees.index'))
            ->post(route('admin.employees.bulk-extend'), [
                'employee_ids' => [$first->id, $second->id],
                'valid_to_dt' => '2027-12-31 18:00',
            ])
            ->assertRedirect(route('admin.employees.index'))
            ->assertSessionHas('success', fn (string $message): bool => str_contains($message, '2 ta xodim')
                && str_contains($message, 'Sinxronizatsiya'));

        foreach ([$first, $second] as $employee) {
            $employee->refresh();
            $this->assertSame('2027-12-31 18:00', $employee->valid_to_dt->format('Y-m-d H:i'));
            $this->assertNull($employee->expiry_warned_at);
            $this->assertNull($employee->expiry_notified_at);
        }

        $this->assertSame('2026-11-01 18:00', $untouched->fresh()->valid_to_dt->format('Y-m-d H:i'));
    }

    public function test_end_date_must_be_in_the_future(): void
    {
        $employee = $this->expired('Ali');

        $this->actingAs($this->createUserWithPermissions())
            ->post(route('admin.employees.bulk-extend'), ['employee_ids' => [$employee->id], 'valid_to_dt' => '2026-11-09 18:00'])
            ->assertSessionHasErrors(['valid_to_dt' => 'Yangi tugash sanasi kelajakda bo‘lishi kerak.']);
    }

    public function test_end_date_cannot_be_before_an_employees_start(): void
    {
        $employee = Employee::factory()->create(['valid_from_dt' => '2028-01-01 09:00', 'valid_to_dt' => '2028-06-01 18:00']);

        $this->actingAs($this->createUserWithPermissions())
            ->post(route('admin.employees.bulk-extend'), ['employee_ids' => [$employee->id], 'valid_to_dt' => '2027-12-31 18:00'])
            ->assertSessionHasErrors('valid_to_dt');

        $this->assertSame('2028-06-01', $employee->fresh()->valid_to_dt->toDateString());
    }

    public function test_extending_needs_the_edit_permission(): void
    {
        $user = $this->createUserWithPermissions([config('menu_permissions.resources')['employees']]);
        $user->revokePermissionTo(config('menu_permissions.actions.employees.edit'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $employee = $this->expired('Ali');

        $this->actingAs($user->fresh())
            ->post(route('admin.employees.bulk-extend'), ['employee_ids' => [$employee->id], 'valid_to_dt' => '2027-12-31 18:00'])
            ->assertForbidden();
    }

    public function test_user_cannot_extend_another_departments_employee(): void
    {
        $user = $this->createDepartmentUserWithPermissions(Department::factory()->create()->id);
        $foreign = $this->expired('Begona');

        $this->actingAs($user)
            ->post(route('admin.employees.bulk-extend'), ['employee_ids' => [$foreign->id], 'valid_to_dt' => '2027-12-31 18:00'])
            ->assertSessionHasErrors('employee_ids');

        $this->assertSame('2026-11-01', $foreign->fresh()->valid_to_dt->toDateString());
    }

    public function test_list_errors_are_in_uzbek(): void
    {
        $employee = $this->expired('Ali');
        $admin = $this->createUserWithPermissions();

        $this->actingAs($admin)
            ->post(route('admin.employees.bulk-extend'), ['employee_ids' => [$employee->id, $employee->id], 'valid_to_dt' => '2027-12-31 18:00'])
            ->assertSessionHasErrors(['employee_ids.0' => 'Bitta xodim ro‘yxatda ikki marta kelgan.']);

        $this->actingAs($admin)
            ->post(route('admin.employees.bulk-extend'), ['employee_ids' => range(1, 501), 'valid_to_dt' => '2027-12-31 18:00'])
            ->assertSessionHasErrors(['employee_ids' => 'Bir martada ko‘pi bilan 500 ta xodimning muddatini uzaytirish mumkin.']);

        $this->actingAs($admin)
            ->post(route('admin.employees.bulk-extend'), ['employee_ids' => [], 'valid_to_dt' => '2027-12-31 18:00'])
            ->assertSessionHasErrors(['employee_ids' => 'Kamida bitta xodimni tanlang.']);

        $this->assertSame('2026-11-01', $employee->fresh()->valid_to_dt->toDateString());
    }

    public function test_sync_page_gives_the_current_end_date(): void
    {
        $this->expired('Ali');

        $this->actingAs($this->createUserWithPermissions())
            ->get(route('admin.sync.index'))
            ->assertInertia(fn (Assert $page) => $page->where('employees.0.valid_to_dt', '2026-11-01 18:00'));
    }

    private function expired(string $name): Employee
    {
        return Employee::factory()->create([
            'name' => $name,
            'department_id' => Department::factory(),
            'valid_from_dt' => '2026-01-01 09:00',
            'valid_to_dt' => '2026-11-01 18:00',
            'expiry_warned_at' => '2026-10-29 08:00',
            'expiry_notified_at' => '2026-11-02 08:00',
        ]);
    }
}
