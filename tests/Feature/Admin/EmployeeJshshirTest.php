<?php

namespace Tests\Feature\Admin;

use App\Exports\EmployeesExport;
use App\Models\Category;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class EmployeeJshshirTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    private const JSHSHIR = '12345678901234';

    private const LENGTH_ERROR = 'JSHSHIR aniq 14 xonali son bo‘lishi kerak.';

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNumbers(): array
    {
        return [
            '13 xona' => ['1234567890123'],
            '15 xona' => ['123456789012345'],
            'harf bor' => ['1234567890123A'],
        ];
    }

    #[DataProvider('invalidNumbers')]
    public function test_new_employee_needs_exactly_14_digits(string $number): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->payload(['card' => $number]))
            ->assertSessionHasErrors(['card' => self::LENGTH_ERROR]);

        $this->assertDatabaseMissing('employees', ['card' => $number]);
    }

    public function test_new_employee_is_saved_with_14_digits(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('employees', ['card' => self::JSHSHIR]);
    }

    public function test_number_is_required_and_never_suggested(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.employees.store'), $this->payload(['card' => '']))
            ->assertSessionHasErrors(['card' => 'JSHSHIR kiriting.']);

        $this->actingAs($admin)
            ->get(route('admin.employees.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->missing('suggestedCard'));
    }

    public function test_duplicate_number_is_rejected_across_departments(): void
    {
        Employee::factory()->create(['card' => self::JSHSHIR, 'department_id' => Department::factory()]);

        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), $this->payload())
            ->assertSessionHasErrors(['card' => 'Bu JSHSHIR boshqa xodimda bor.']);
    }

    public function test_employee_with_an_old_number_can_still_be_edited(): void
    {
        $employee = Employee::factory()->create(['card' => '1234567890123']);

        $this->actingAs($this->admin())
            ->put(route('admin.employees.update', $employee), $this->updatePayload($employee, ['last_name' => 'Yangi', 'first_name' => 'ism', 'middle_name' => null]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Yangi ism', $employee->fresh()->name);
        $this->assertSame('1234567890123', $employee->fresh()->card);
    }

    public function test_number_can_be_changed_only_to_14_digits(): void
    {
        $employee = Employee::factory()->create(['card' => '1234567890123']);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('admin.employees.update', $employee), $this->updatePayload($employee, ['card' => '123456789012']))
            ->assertSessionHasErrors(['card' => self::LENGTH_ERROR]);

        $this->actingAs($admin)
            ->put(route('admin.employees.update', $employee), $this->updatePayload($employee, ['card' => self::JSHSHIR]))
            ->assertSessionHasNoErrors();

        $this->assertSame(self::JSHSHIR, $employee->fresh()->card);
    }

    public function test_number_is_locked_while_employee_is_on_a_device(): void
    {
        $admin = $this->admin();
        $employee = Employee::factory()->create(['card' => '1234567890123']);
        $employee->devices()->attach(
            Device::factory()->create(['user_id' => $admin->id, 'category_id' => Category::factory()])->id,
            ['synced_at' => now()],
        );

        $this->actingAs($admin)
            ->put(route('admin.employees.update', $employee), $this->updatePayload($employee, ['card' => self::JSHSHIR]))
            ->assertSessionHasErrors(['card' => 'JSHSHIRni o‘zgartirish uchun avval xodimni barcha qurilmalardan o‘chiring.']);

        $this->assertSame('1234567890123', $employee->fresh()->card);
    }

    public function test_excel_keeps_the_full_number_as_text(): void
    {
        Employee::factory()->create(['card' => self::JSHSHIR]);

        $path = tempnam(sys_get_temp_dir(), 'jshshir').'.xlsx';
        file_put_contents($path, Excel::raw(new EmployeesExport(Employee::query()), ExcelWriter::XLSX));
        $sheet = IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        $this->assertSame('JSHSHIR', $sheet->getCell('C1')->getValue());
        $this->assertSame(self::JSHSHIR, $sheet->getCell('C2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('C2')->getDataType());
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
            'last_name' => 'Yangi',
            'first_name' => 'Xodim',
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
            'gender' => $employee->gender,
            'position' => $employee->position,
            'department_id' => $employee->department_id,
            'schedule_id' => $employee->schedule_id,
            'valid_from_dt' => $employee->valid_from_dt->format('Y-m-d\TH:i'),
            'valid_to_dt' => $employee->valid_to_dt->format('Y-m-d\TH:i'),
            ...$overrides,
        ];
    }
}
