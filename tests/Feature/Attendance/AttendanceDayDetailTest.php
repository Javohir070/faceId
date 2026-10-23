<?php

namespace Tests\Feature\Attendance;

use App\Enums\NoteType;
use App\Models\Category;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Note;
use App\Models\Report;
use App\Models\Schedule;
use App\Services\Attendance\AttendanceGridService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceDayDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-23 12:00:00'));
    }

    public function test_day_detail_lists_punches_and_notes(): void
    {
        $employee = $this->employee();
        $device = Device::factory()->create([
            'name' => '1-kirish',
            'category_id' => Category::factory()->create(['name' => 'Bosh bino'])->id,
        ]);
        foreach ([['08:51', 'in'], ['13:04', 'out'], ['13:55', 'in'], ['18:13', 'out']] as [$time, $dir]) {
            $factory = Report::factory()->forEmployee($employee)->forDevice($device);
            ($dir === 'in' ? $factory->entry() : $factory->exit())->create(['occurred_at' => "2026-10-13 $time"]);
        }
        Note::factory()->create([
            'employee_id' => $employee->id,
            'dt' => '2026-10-13',
            'type' => NoteType::LeftEarly,
            'note' => 'Shifokorga',
        ]);

        $day = app(AttendanceGridService::class)->day($employee, '2026-10-13', now());

        $this->assertSame(
            ['excused', '08:51', '18:13', false, '8 s 22 d', '09:00–18:00'],
            [$day['status'], $day['in'], $day['out'], $day['inside'], $day['worked'], $day['schedule']],
        );
        $this->assertSame(['id' => $employee->id, 'name' => 'S. Turdiyev', 'position' => 'Katta ilmiy xodim'], $day['employee']);
        $this->assertSame('2026-10-13', $day['date']);
        $this->assertCount(4, $day['punches']);
        $this->assertSame(
            ['time' => '13:04', 'type' => 'exit', 'label' => 'Chiqdi', 'place' => 'Bosh bino · 1-kirish'],
            $day['punches'][1],
        );
        $this->assertSame('Kirdi', $day['punches'][0]['label']);
        $this->assertSame('Erta ketdi', $day['notes'][0]['type_label']);
        $this->assertSame('Shifokorga', $day['notes'][0]['note']);
        $this->assertSame(NoteType::LeftEarly->value, $day['notes'][0]['type']);
    }

    public function test_day_detail_without_device_or_notes(): void
    {
        $employee = $this->employee();
        Report::factory()->forEmployee($employee)->entry()->create([
            'occurred_at' => '2026-10-23 08:10',
            'device_id' => null,
        ]);

        $day = app(AttendanceGridService::class)->day($employee, '2026-10-23', now());

        $this->assertSame('Noma’lum qurilma', $day['punches'][0]['place']);
        $this->assertSame([], $day['notes']);
        $this->assertSame(['on_time', true, '0 s 00 d'], [$day['status'], $day['inside'], $day['worked']]);
    }

    private function employee(): Employee
    {
        return Employee::factory()->create([
            'name' => 'S. Turdiyev',
            'position' => 'Katta ilmiy xodim',
            'schedule_id' => Schedule::factory()->create(['enter_time' => '09:00', 'exit_time' => '18:00'])->id,
            'valid_from_dt' => '2026-01-01 00:00:00',
            'valid_to_dt' => '2027-12-31 00:00:00',
        ]);
    }
}
