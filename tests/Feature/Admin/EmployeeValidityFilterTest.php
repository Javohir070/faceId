<?php

namespace Tests\Feature\Admin;

use App\Exports\EmployeesExport;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Facades\Excel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class EmployeeValidityFilterTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-09 10:00'));
        config(['telegram.employee_expiry_warn_days' => 3]);

        $this->admin = $this->createUserWithPermissions([config('menu_permissions.resources')['employees']]);

        $this->employee('Tugagan', '2026-01-01 09:00', '2026-11-08 18:00');
        $this->employee('Yaqin', '2026-01-01 09:00', '2026-11-11 18:00');
        $this->employee('Faol', '2026-01-01 09:00', '2027-01-01 18:00');
        $this->employee('Boshlanmagan', '2026-11-12 09:00', '2027-01-01 18:00');
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function filters(): array
    {
        return [
            'muddati tugagan' => ['expired', ['Tugagan']],
            'tugashiga yaqin' => ['expiring', ['Yaqin']],
            'faol (yaqinlar ham)' => ['active', ['Faol', 'Yaqin']],
            'hali boshlanmagan' => ['upcoming', ['Boshlanmagan']],
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('filters')]
    public function test_list_is_filtered_by_validity(string $validity, array $expected): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.employees.index', ['validity' => $validity]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.validity', $validity)
                ->where('employees.data', fn ($rows): bool => collect($rows)->pluck('name')->sort()->values()->all()
                    === collect($expected)->sort()->values()->all()));
    }

    public function test_each_row_carries_its_validity_status(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.employees.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('employees.data', fn ($rows): bool => collect($rows)->pluck('validity', 'name')->sortKeys()->all() === [
                    'Boshlanmagan' => 'upcoming',
                    'Faol' => 'active',
                    'Tugagan' => 'expired',
                    'Yaqin' => 'expiring',
                ]));
    }

    public function test_unknown_validity_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.employees.index', ['validity' => 'hammasi']))
            ->assertSessionHasErrors('validity');
    }

    public function test_excel_export_uses_the_same_filter(): void
    {
        Excel::fake();

        $this->actingAs($this->admin)
            ->get(route('admin.employees.export', ['validity' => 'expired']))
            ->assertOk();

        Excel::assertDownloaded('xodimlar.xlsx', fn (EmployeesExport $export): bool => $export->query()->pluck('name')->all() === ['Tugagan']);
    }

    private function employee(string $name, string $from, string $to): void
    {
        Employee::factory()->create([
            'name' => $name,
            'valid_from_dt' => $from,
            'valid_to_dt' => $to,
        ]);
    }
}
