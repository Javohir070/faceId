<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class EmployeeCountryTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_employee_saved_without_country_defaults_to_uzbekistan(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->storePayload('11110001'))
            ->assertRedirect(route('admin.employees.index'));

        $this->assertDatabaseHas('employees', ['card' => '11110001', 'country' => 'UZ']);
    }

    public function test_employee_can_be_saved_with_foreign_country(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->storePayload('11110002') + ['country' => 'KZ'])
            ->assertRedirect(route('admin.employees.index'));

        $this->assertDatabaseHas('employees', ['card' => '11110002', 'country' => 'KZ']);
    }

    public function test_unknown_country_code_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->storePayload('11110003') + ['country' => 'XX'])
            ->assertSessionHasErrors('country');

        $this->assertDatabaseMissing('employees', ['card' => '11110003']);
    }

    public function test_update_without_country_keeps_existing_value(): void
    {
        $employee = Employee::factory()->create(['card' => '11110004', 'country' => 'JP']);

        $payload = $this->storePayload('11110004');
        unset($payload['face']);
        $payload['department_id'] = $employee->department_id;

        $this->actingAs($this->admin())
            ->put(route('admin.employees.update', $employee), $payload)
            ->assertRedirect(route('admin.employees.index'));

        $this->assertSame('JP', $employee->fresh()->country);
    }

    public function test_form_lists_countries_with_uzbek_names_uzbekistan_first(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.employees.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('countries', 249)
                ->where('countries.0', ['value' => 'UZ', 'label' => 'Oʻzbekiston'])
                ->where('countries', fn ($countries) => collect($countries)->firstWhere('value', 'KZ')['label'] === 'Qozogʻiston'));
    }

    public function test_show_page_has_country_name(): void
    {
        $employee = Employee::factory()->create(['country' => 'JP']);

        $this->actingAs($this->admin())
            ->get(route('admin.employees.show', $employee))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('employee.country', 'JP')
                ->where('employee.country_name', 'Yaponiya'));
    }

    public function test_is_foreign(): void
    {
        $this->assertFalse((new Employee(['country' => 'UZ']))->isForeign());
        $this->assertTrue((new Employee(['country' => 'KZ']))->isForeign());
    }

    private function admin(): User
    {
        return $this->createUserWithPermissions([
            config('menu_permissions.resources')['employees'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function storePayload(string $card): array
    {
        return [
            'name' => 'Aigerim Nurlanova',
            'card' => $card,
            'gender' => Employee::GENDER_FEMALE,
            'position' => 'Mehmon',
            'department_id' => Department::factory()->create()->id,
            'group_id' => null,
            'schedule_id' => Schedule::factory()->create()->id,
            'valid_from_dt' => '2026-01-01T09:00',
            'valid_to_dt' => '2026-12-31T18:00',
            'face' => UploadedFile::fake()->image('face.jpg', 200, 200),
        ];
    }
}
