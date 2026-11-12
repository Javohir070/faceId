<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Schedule;
use App\Services\Employees\EmployeeName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

/**
 * Xodim formasi: Familiya, Ism, Otasining ismi — `name` server tomonida shulardan yig‘iladi.
 */
class EmployeeNamePartsTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    private const JSHSHIR = '12345678901234';

    public function test_store_assembles_the_full_name_from_the_three_parts(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->payload([
                'last_name' => 'Valiyev',
                'first_name' => 'Ali',
                'middle_name' => 'Akmal o‘g‘li',
                // Formadan kelgan to‘liq ism e’tiborga olinmaydi — uni server yig‘adi.
                'name' => 'Boshqa Ism',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.employees.index'));

        $employee = Employee::query()->where('card', self::JSHSHIR)->firstOrFail();

        $this->assertSame('Valiyev Ali Akmal o‘g‘li', $employee->name);
        $this->assertSame('Valiyev', $employee->last_name);
        $this->assertSame('Ali', $employee->first_name);
        $this->assertSame('Akmal o‘g‘li', $employee->middle_name);
    }

    public function test_middle_name_is_optional(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->payload([
                'last_name' => '  Karimova ',
                'first_name' => 'Dilnoza',
                'middle_name' => '',
            ]))
            ->assertSessionHasNoErrors();

        $employee = Employee::query()->where('card', self::JSHSHIR)->firstOrFail();

        $this->assertSame('Karimova Dilnoza', $employee->name);
        $this->assertSame('Karimova', $employee->last_name);
        $this->assertNull($employee->middle_name);
    }

    public function test_update_reassembles_the_full_name(): void
    {
        $employee = Employee::factory()->create(['name' => 'Valiyev Ali Akmal o‘g‘li']);

        $this->actingAs($this->admin())
            ->put(route('admin.employees.update', $employee), $this->updatePayload($employee, [
                'last_name' => 'Valiyeva',
                'first_name' => 'Aziza',
                'middle_name' => '',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.employees.index'));

        $employee->refresh();
        $this->assertSame('Valiyeva Aziza', $employee->name);
        $this->assertSame('Valiyeva', $employee->last_name);
        $this->assertSame('Aziza', $employee->first_name);
        $this->assertNull($employee->middle_name);
    }

    public function test_last_name_and_first_name_are_required(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->payload([
                'last_name' => '',
                'first_name' => '   ',
                'name' => 'Valiyev Ali',
            ]))
            ->assertSessionHasErrors([
                'last_name' => 'Familiyani kiriting.',
                'first_name' => 'Ismni kiriting.',
            ]);

        $this->assertDatabaseCount('employees', 0);

        $employee = Employee::factory()->create(['name' => 'Valiyev Ali']);

        $this->actingAs($this->admin())
            ->put(route('admin.employees.update', $employee), $this->updatePayload($employee, ['first_name' => '']))
            ->assertSessionHasErrors(['first_name' => 'Ismni kiriting.']);

        $this->assertSame('Valiyev Ali', $employee->fresh()->name);
    }

    public function test_each_part_is_at_most_80_characters(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->payload([
                'last_name' => str_repeat('a', 81),
                'first_name' => str_repeat('b', 81),
                'middle_name' => str_repeat('c', 81),
            ]))
            ->assertSessionHasErrors([
                'last_name' => 'Familiya 80 belgidan oshmasligi kerak.',
                'first_name' => 'Ism 80 belgidan oshmasligi kerak.',
                'middle_name' => 'Otasining ismi 80 belgidan oshmasligi kerak.',
            ]);

        $this->assertDatabaseCount('employees', 0);
    }

    public function test_edit_and_show_pages_receive_the_three_parts(): void
    {
        $employee = Employee::factory()->create(['name' => 'Valiyev Ali Akmal o‘g‘li']);

        foreach (['admin.employees.edit', 'admin.employees.show'] as $routeName) {
            $this->actingAs($this->admin())
                ->get(route($routeName, $employee))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('employee.name', 'Valiyev Ali Akmal o‘g‘li')
                    ->where('employee.last_name', 'Valiyev')
                    ->where('employee.first_name', 'Ali')
                    ->where('employee.middle_name', 'Akmal o‘g‘li'));
        }
    }

    public function test_factory_derives_the_parts_from_the_full_name(): void
    {
        $named = Employee::factory()->create(['name' => 'Karimova Dilnoza']);
        $this->assertSame(['Karimova', 'Dilnoza', null], [$named->last_name, $named->first_name, $named->middle_name]);

        $random = Employee::factory()->create();
        $this->assertNotNull($random->last_name);
        $this->assertNotNull($random->first_name);
        $this->assertSame($random->name, EmployeeName::join($random->last_name, $random->first_name, $random->middle_name));
    }

    private function admin()
    {
        return $this->createUserWithPermissions([config('menu_permissions.resources')['employees']]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'last_name' => 'Valiyev',
            'first_name' => 'Ali',
            'card' => self::JSHSHIR,
            'gender' => 1,
            'department_id' => Department::factory()->create()->id,
            'schedule_id' => Schedule::factory()->create()->id,
            'valid_from_dt' => '2026-11-01T09:00',
            'valid_to_dt' => '2027-11-01T09:00',
            'face' => UploadedFile::fake()->image('face.jpg'),
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function updatePayload(Employee $employee, array $overrides = []): array
    {
        return [
            'last_name' => $employee->last_name,
            'first_name' => $employee->first_name,
            'middle_name' => $employee->middle_name,
            'card' => $employee->card,
            'gender' => $employee->gender ?? 1,
            'department_id' => $employee->department_id,
            'schedule_id' => $employee->schedule_id,
            'valid_from_dt' => $employee->valid_from_dt->format('Y-m-d\TH:i'),
            'valid_to_dt' => $employee->valid_to_dt->format('Y-m-d\TH:i'),
            ...$overrides,
        ];
    }
}
