<?php

namespace Tests\Feature\Admin;

use App\Exports\EmployeesExport;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class EmployeeExportTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    public function test_authorized_user_can_download_filtered_employees_excel(): void
    {
        Excel::fake();

        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['employees'],
        ]);

        $department = Department::factory()->create(['name' => 'IT']);
        $otherDepartment = Department::factory()->create(['name' => 'HR']);
        Schedule::factory()->create();

        Employee::factory()->create(['name' => 'Ali Valiyev', 'department_id' => $department->id]);
        Employee::factory()->create(['name' => 'Vali Aliyev', 'department_id' => $otherDepartment->id]);

        $this->actingAs($admin)
            ->get(route('admin.employees.export', ['department_id' => $department->id]))
            ->assertOk();

        Excel::assertDownloaded('xodimlar.xlsx', function (EmployeesExport $export) {
            return $export->query()->count() === 1;
        });
    }

    public function test_unauthorized_user_cannot_download_employees_excel(): void
    {
        $user = $this->createUserWithPermissions([
            config('menu_permissions.home'),
        ]);

        $this->actingAs($user)
            ->get(route('admin.employees.export'))
            ->assertForbidden();
    }
}
