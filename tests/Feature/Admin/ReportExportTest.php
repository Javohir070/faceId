<?php

namespace Tests\Feature\Admin;

use App\Exports\ReportsExport;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Report;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    public function test_authorized_user_can_download_filtered_reports_excel(): void
    {
        Excel::fake();

        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['reports'],
        ]);

        $department = Department::factory()->create(['name' => 'IT']);
        $employee = Employee::factory()->create(['department_id' => $department->id]);
        $device = Device::factory()->create(['name' => 'Kirish 1']);

        Report::factory()->forEmployee($employee)->forDevice($device)->entry()->create([
            'occurred_at' => now(),
        ]);
        Report::factory()->exit()->create(['occurred_at' => now()]);

        $this->actingAs($admin)
            ->get(route('admin.reports.export', ['type' => Report::TYPE_ENTRY]))
            ->assertOk();

        Excel::assertDownloaded('hisobotlar.xlsx', function (ReportsExport $export) {
            return $export->collection()->count() === 1
                && $export->headings()[0] === '№';
        });
    }

    public function test_unauthorized_user_cannot_download_reports_excel(): void
    {
        $user = $this->createUserWithPermissions([
            config('menu_permissions.home'),
        ]);

        $this->actingAs($user)
            ->get(route('admin.reports.export'))
            ->assertForbidden();
    }

    public function test_late_export_uses_late_headings(): void
    {
        Excel::fake();

        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['reports'],
        ]);

        $department = Department::factory()->create();
        $schedule = Schedule::factory()->create(['enter_time' => '09:00', 'exit_time' => '18:00']);
        $employee = Employee::factory()->create([
            'department_id' => $department->id,
            'schedule_id' => $schedule->id,
            'name' => 'Late Ali',
        ]);
        $device = Device::factory()->create();
        Report::factory()->forEmployee($employee)->forDevice($device)->entry()->create([
            'occurred_at' => '2026-08-04 09:20:00',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.export', [
                'type' => 3,
                'date_from' => '2026-08-04 00:00:00',
                'date_to' => '2026-08-04 23:59:59',
            ]))
            ->assertOk();

        Excel::assertDownloaded('hisobotlar.xlsx', function (ReportsExport $export) {
            return $export->headings() === ['№', 'Ism', 'Guruh', 'Lavozim', 'Sana', 'Vaqt', 'Kechikish', 'Sabab']
                && $export->collection()->count() === 1;
        });
    }
}
