<?php

namespace Tests\Feature\Admin;

use App\Enums\NoteType;
use App\Exports\AttendanceExport;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Note;
use App\Models\Report;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class AttendanceExportTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    public function test_export_downloads_grid(): void
    {
        $this->travelTo(Carbon::parse('2026-10-23 12:00:00'));
        Excel::fake();

        $employee = Employee::factory()->create([
            'name' => 'Ali Valiyev',
            'position' => 'Muhandis',
            'department_id' => Department::factory()->create(['name' => 'IT'])->id,
            'schedule_id' => Schedule::factory()->create(['enter_time' => '09:00', 'exit_time' => '18:00'])->id,
            'valid_from_dt' => '2026-01-01 00:00:00',
            'valid_to_dt' => '2027-12-31 00:00:00',
        ]);
        foreach ([['2026-10-12 08:00', Report::TYPE_ENTRY], ['2026-10-12 18:00', Report::TYPE_EXIT], ['2026-10-23 08:30', Report::TYPE_ENTRY]] as [$at, $type]) {
            Report::query()->create([
                'employee_id' => $employee->id,
                'department_id' => $employee->department_id,
                'employee_name' => $employee->name,
                'type' => $type,
                'occurred_at' => $at,
            ]);
        }
        Note::factory()->create(['employee_id' => $employee->id, 'dt' => '2026-10-13', 'type' => NoteType::Absent]);

        $admin = $this->createUserWithPermissions([config('menu_permissions.resources')['attendance']]);

        $this->actingAs($admin)
            ->get(route('admin.attendance.export', ['date' => '2026-10-23']))
            ->assertOk();

        Excel::assertDownloaded('davomat_2026-10-12_2026-10-23.xlsx', function (AttendanceExport $export): bool {
            $headings = $export->headings();
            $row = $export->map($export->collection()->first());

            return $headings[0] === '№'
                && $headings[4] === '12.10 Du'
                && $headings[13] === '23.10 Ju'
                && $headings[14] === 'Ishladi'
                && $row[1] === 'IT'
                && $row[2] === 'Ali Valiyev'
                && $row[3] === 'Muhandis'
                && $row[4] === '08:00–18:00'
                && $row[5] === 'Sababli'
                && $row[6] === 'Kelmadi'
                && $row[13] === '08:30–ishda'
                && $row[14] === '9 s 00 d';
        });
    }

    public function test_export_requires_attendance_permission(): void
    {
        $user = $this->createUserWithPermissions([config('menu_permissions.resources')['reports']]);

        $this->actingAs($user)->get(route('admin.attendance.export'))->assertForbidden();
    }
}
