<?php

namespace Tests\Feature\Admin;

use App\Exports\ReportsExport;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Report;
use App\Models\Schedule;
use App\Services\ReportQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
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
            return count(iterator_to_array($export->generator(), false)) === 1
                && $export->headings()[0] === '№';
        });
    }

    public function test_raw_export_writes_every_row_with_fixed_column_widths(): void
    {
        $employee = Employee::factory()->create(['name' => 'Excel Ali']);
        $device = Device::factory()->create(['name' => 'Kirish 1']);

        foreach (['2026-08-04 09:00:00', '2026-08-04 18:00:00', '2026-08-05 09:05:00'] as $moment) {
            Report::factory()->forEmployee($employee)->forDevice($device)->entry()->create(['occurred_at' => $moment]);
        }

        $service = app(ReportQueryService::class);
        $path = tempnam(sys_get_temp_dir(), 'hisobot').'.xlsx';
        file_put_contents($path, Excel::raw(new ReportsExport($service->forExport([]), $service->columns([])), ExcelWriter::XLSX));
        $sheet = IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        $this->assertSame(4, $sheet->getHighestDataRow());
        $this->assertSame('Excel Ali', $sheet->getCell('B2')->getValue());
        $this->assertSame('2026-08-05 09:05:00', collect($sheet->rangeToArray('A2:H2')[0])->first(fn ($value): bool => str_starts_with((string) $value, '2026-')));
        $this->assertGreaterThan(20, $sheet->getColumnDimension('B')->getWidth());
        $this->assertFalse($sheet->getColumnDimension('B')->getAutoSize());
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
                && count(iterator_to_array($export->generator(), false)) === 1;
        });
    }
}
