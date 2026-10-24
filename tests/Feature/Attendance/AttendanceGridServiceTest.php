<?php

namespace Tests\Feature\Attendance;

use App\Enums\NoteType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Note;
use App\Models\Report;
use App\Models\Schedule;
use App\Services\Attendance\AttendanceGridService;
use App\Services\Attendance\AttendancePeriod;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AttendanceGridServiceTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceGridService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-23 12:00:00'));
        $this->service = app(AttendanceGridService::class);
    }

    public function test_screenshot_example(): void
    {
        $employee = $this->employee();
        $days = [
            '2026-10-12' => ['08:14', '18:46'], '2026-10-13' => ['08:51', '18:13'], '2026-10-14' => ['06:59', '18:30'],
            '2026-10-15' => ['07:36', '18:47'], '2026-10-16' => ['08:13', '17:11'], '2026-10-19' => ['10:05', '18:15'],
            '2026-10-20' => ['08:12', '18:32'], '2026-10-21' => ['08:49', '18:49'], '2026-10-22' => ['06:57', '17:17'],
        ];
        foreach ($days as $date => [$in, $out]) {
            $this->punch($employee, "$date $in", 'in');
            $this->punch($employee, "$date $out", 'out');
        }
        $this->punch($employee, '2026-10-23 06:34', 'in');
        $this->note($employee, '2026-10-16', NoteType::LeftEarly);

        $result = $this->build([$employee]);
        $s = $result['stats'];

        $this->assertSame([10, 10, '08:03'], [$s['elapsed_days'], $s['work_days'], $s['avg_arrival']]);
        $this->assertSame([8, 1, 1, 0, 2], [$s['on_time'], $s['late'], $s['excused'], $s['absent'], $s['early']]);
        $this->assertSame(['on_time' => 80, 'late' => 10, 'excused' => 10, 'absent' => 0], $s['percent']);
        $this->assertSame(['81 s 24 d', '72 s 00 d'], [$s['worked'], $s['plan']]);

        $cells = $this->cells($result);
        $this->assertSame('excused', $cells['2026-10-16']['status']);
        $this->assertSame('late', $cells['2026-10-19']['status']);
        $this->assertSame(
            ['on_time', '06:34', null, true],
            [$cells['2026-10-23']['status'], $cells['2026-10-23']['in'], $cells['2026-10-23']['out'], $cells['2026-10-23']['inside']],
        );
        $this->assertSame(
            ['08:14', '18:46', 34320],
            [$cells['2026-10-12']['in'], $cells['2026-10-12']['out'], $cells['2026-10-12']['worked_seconds']],
        );
        $this->assertSame('81 s 24 d', $result['rows'][0]['worked']);
        $this->assertSame(293040, $result['rows'][0]['worked_seconds']);
    }

    public function test_absent_and_excused_absence(): void
    {
        $employee = $this->employee();
        $this->note($employee, '2026-10-14', NoteType::Absent);

        $result = $this->build([$employee]);
        $s = $result['stats'];
        $cells = $this->cells($result);

        $this->assertSame([0, 0, 1, 8], [$s['on_time'], $s['late'], $s['excused'], $s['absent']]);
        $this->assertSame('excused', $cells['2026-10-14']['status']);
        $this->assertSame('absent', $cells['2026-10-12']['status']);
        $this->assertSame('pending', $cells['2026-10-23']['status']);
        $this->assertSame(['72 s 00 d', '0 s 00 d'], [$s['plan'], $s['worked']]);
        $this->assertNull($s['avg_arrival']);
    }

    public function test_today_without_entry_is_pending_and_not_counted(): void
    {
        $employee = $this->employee();
        $this->punch($employee, '2026-10-22 08:00', 'in');
        $this->punch($employee, '2026-10-22 18:00', 'out');

        $s = $this->build([$employee])['stats'];

        $this->assertSame([1, 8], [$s['on_time'], $s['absent']]);
        $this->assertSame(['on_time' => 11, 'late' => 0, 'excused' => 0, 'absent' => 89], $s['percent']);
        $this->assertSame('pending', $this->cells($this->build([$employee]))['2026-10-23']['status']);
    }

    public function test_future_days_are_blank(): void
    {
        $this->travelTo(Carbon::parse('2026-10-21 12:00:00'));
        $employee = $this->employee();

        $result = $this->build([$employee]);
        $cells = $this->cells($result);

        $this->assertSame(['future', 'future'], [$cells['2026-10-22']['status'], $cells['2026-10-23']['status']]);
        $this->assertSame([10, 8], [$result['stats']['work_days'], $result['stats']['elapsed_days']]);
        $this->assertSame(7, $result['stats']['absent']);
    }

    public function test_late_by_one_second(): void
    {
        $employee = $this->employee();
        $this->punch($employee, '2026-10-20 09:00:01', 'in');
        $this->punch($employee, '2026-10-21 09:00:00', 'in');

        $cells = $this->cells($this->build([$employee]));

        $this->assertSame('late', $cells['2026-10-20']['status']);
        $this->assertSame('on_time', $cells['2026-10-21']['status']);
    }

    public function test_days_outside_employee_validity_are_inactive(): void
    {
        $employee = $this->employee(['valid_from_dt' => '2026-10-15 00:00:00']);

        $result = $this->build([$employee]);
        $cells = $this->cells($result);

        $this->assertSame(
            ['inactive', 'inactive', 'inactive', 'absent'],
            [$cells['2026-10-12']['status'], $cells['2026-10-13']['status'], $cells['2026-10-14']['status'], $cells['2026-10-15']['status']],
        );
        $this->assertSame(6, $result['stats']['absent']);
        $this->assertSame('48 s 00 d', $result['stats']['plan']);
    }

    public function test_night_shift_attributes_morning_exit_to_previous_day(): void
    {
        $schedule = Schedule::factory()->create(['enter_time' => '20:00', 'exit_time' => '08:00']);
        $employee = $this->employee(['schedule_id' => $schedule->id]);
        $this->punch($employee, '2026-10-19 19:55', 'in');
        $this->punch($employee, '2026-10-20 08:05', 'out');

        $result = $this->build([$employee]);
        $cells = $this->cells($result);

        $this->assertSame(
            ['on_time', '19:55', '08:05', 40200],
            [$cells['2026-10-19']['status'], $cells['2026-10-19']['in'], $cells['2026-10-19']['out'], $cells['2026-10-19']['worked_seconds']],
        );
        $this->assertSame([null, null], [$cells['2026-10-20']['in'], $cells['2026-10-20']['out']]);
        $this->assertSame('absent', $cells['2026-10-20']['status']);
        // 9 yakunlangan kun × (12 soat − 1 soat tushlik)
        $this->assertSame(9 * 39600, $result['stats']['plan_seconds']);
    }

    public function test_weekend_punches_are_ignored(): void
    {
        $employee = $this->employee();
        $this->punch($employee, '2026-10-17 10:00', 'in');

        $result = $this->build([$employee]);

        $this->assertArrayNotHasKey('2026-10-17', $this->cells($result));
        $this->assertSame([0, 9], [$result['stats']['on_time'], $result['stats']['absent']]);
    }

    public function test_early_leave_counts_only_finished_days(): void
    {
        $employee = $this->employee();
        $this->punch($employee, '2026-10-22 08:00', 'in');
        $this->punch($employee, '2026-10-22 17:00', 'out');
        $this->punch($employee, '2026-10-23 08:00', 'in');
        $this->punch($employee, '2026-10-23 13:00', 'out');
        $this->punch($employee, '2026-10-23 14:00', 'in');

        $result = $this->build([$employee]);
        $cells = $this->cells($result);

        $this->assertTrue($cells['2026-10-23']['inside']);
        $this->assertSame('13:00', $cells['2026-10-23']['out']);
        $this->assertFalse($cells['2026-10-23']['early']);
        $this->assertTrue($cells['2026-10-22']['early']);
        $this->assertSame(1, $result['stats']['early']);
        $this->assertSame(0, $cells['2026-10-23']['worked_seconds']);
    }

    public function test_employee_without_valid_day_is_omitted(): void
    {
        $employee = $this->employee([
            'valid_from_dt' => '2026-01-01 00:00:00',
            'valid_to_dt' => '2026-10-01 00:00:00',
        ]);

        $result = $this->build([$employee]);

        $this->assertSame([], $result['rows']);
        $this->assertSame([0, 0, 10], [$result['stats']['on_time'], $result['stats']['absent'], $result['stats']['work_days']]);
    }

    public function test_exit_only_today_is_pending_and_not_counted(): void
    {
        $employee = $this->employee();
        $this->punch($employee, '2026-10-23 10:00', 'out');

        $result = $this->build([$employee]);

        $this->assertSame('pending', $this->cells($result)['2026-10-23']['status']);
        $this->assertSame([0, '72 s 00 d'], [$result['stats']['early'], $result['stats']['plan']]);
    }

    public function test_exit_only_past_day_is_absent_without_early_leave(): void
    {
        $employee = $this->employee();
        $this->punch($employee, '2026-10-22 17:00', 'out');

        $result = $this->build([$employee]);
        $cell = $this->cells($result)['2026-10-22'];

        $this->assertSame(['absent', false], [$cell['status'], $cell['early']]);
        $this->assertSame(0, $result['stats']['early']);
    }

    public function test_today_lunch_exit_does_not_finish_the_day(): void
    {
        $this->travelTo(Carbon::parse('2026-10-23 13:30:00'));
        $employee = $this->employee();
        $this->punch($employee, '2026-10-23 08:00', 'in');
        $this->punch($employee, '2026-10-23 13:00', 'out');

        $result = $this->build([$employee]);
        $cell = $this->cells($result)['2026-10-23'];

        $this->assertSame([false, 0], [$cell['early'], $cell['worked_seconds']]);
        $this->assertSame([0, '72 s 00 d'], [$result['stats']['early'], $result['stats']['plan']]);

        $this->travelTo(Carbon::parse('2026-10-23 18:30:00'));
        $this->punch($employee, '2026-10-23 13:30', 'in');
        $this->punch($employee, '2026-10-23 18:20', 'out');

        $finished = $this->build([$employee]);
        // 08:00–18:20 = 10 s 20 d, tushlik ayirilgach 9 s 20 d
        $this->assertSame(33600, $this->cells($finished)['2026-10-23']['worked_seconds']);
        $this->assertSame('80 s 00 d', $finished['stats']['plan']);
    }

    public function test_night_shift_still_in_progress_after_midnight(): void
    {
        $this->travelTo(Carbon::parse('2026-10-23 03:00:00'));
        $schedule = Schedule::factory()->create(['enter_time' => '20:00', 'exit_time' => '08:00']);
        $employee = $this->employee(['schedule_id' => $schedule->id]);
        $this->punch($employee, '2026-10-22 19:55', 'in');
        $this->punch($employee, '2026-10-23 00:30', 'out');
        $this->punch($employee, '2026-10-23 01:00', 'in');

        $result = $this->build([$employee]);
        $cells = $this->cells($result);

        $this->assertSame(
            [true, false, 0],
            [$cells['2026-10-22']['inside'], $cells['2026-10-22']['early'], $cells['2026-10-22']['worked_seconds']],
        );
        $this->assertSame('future', $cells['2026-10-23']['status']);
        // 12–21.10 — 8 yakunlangan kun × 11 soat; 22.10 smenasi hali tugamagan
        $this->assertSame(8 * 39600, $result['stats']['plan_seconds']);
        $this->assertSame(0, $result['stats']['early']);
    }

    public function test_month_with_many_punches_stays_within_memory_budget(): void
    {
        $schedule = Schedule::factory()->create(['enter_time' => '09:00', 'exit_time' => '18:00']);
        $department = Department::factory()->create();
        $employees = Employee::factory()->count(300)->create([
            'schedule_id' => $schedule->id,
            'department_id' => $department->id,
            'valid_from_dt' => '2026-01-01 00:00:00',
            'valid_to_dt' => '2027-12-31 00:00:00',
        ]);
        $period = AttendancePeriod::make('month', now());
        $rows = [];
        foreach ($employees as $employee) {
            foreach ($period->workDates() as $date) {
                foreach ([['08:50:00', 1], ['13:00:00', 2], ['14:00:00', 1], ['18:10:00', 2]] as [$time, $type]) {
                    $rows[] = [
                        'employee_id' => $employee->id,
                        'department_id' => $department->id,
                        'employee_name' => $employee->name,
                        'type' => $type,
                        'occurred_at' => "$date $time",
                    ];
                }
            }
        }
        foreach (array_chunk($rows, 1000) as $chunk) {
            Report::query()->insert($chunk);
        }
        unset($rows);

        memory_reset_peak_usage();
        $before = memory_get_usage();
        $result = $this->service->build($employees, $period, now());
        $peak = memory_get_peak_usage() - $before;

        $this->assertCount(300, $result['rows']);
        $this->assertLessThan(32 * 1024 * 1024, $peak, 'build() xotira sarfi: '.round($peak / 1048576, 1).' MB');
    }

    public function test_format_duration(): void
    {
        $this->assertSame('81 s 24 d', AttendanceGridService::formatDuration(293040));
        $this->assertSame('0 s 05 d', AttendanceGridService::formatDuration(300));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function employee(array $attributes = []): Employee
    {
        return Employee::factory()->create(array_merge([
            'schedule_id' => Schedule::factory()->create(['enter_time' => '09:00', 'exit_time' => '18:00'])->id,
            'valid_from_dt' => '2026-01-01 00:00:00',
            'valid_to_dt' => '2027-12-31 00:00:00',
        ], $attributes));
    }

    private function punch(Employee $employee, string $at, string $direction): void
    {
        $factory = Report::factory()->forEmployee($employee);
        ($direction === 'in' ? $factory->entry() : $factory->exit())->create(['occurred_at' => $at]);
    }

    private function note(Employee $employee, string $date, NoteType $type): void
    {
        Note::factory()->create(['employee_id' => $employee->id, 'dt' => $date, 'type' => $type]);
    }

    /**
     * @param  list<Employee>  $employees
     * @return array{rows: list<array<string, mixed>>, stats: array<string, mixed>}
     */
    private function build(array $employees): array
    {
        return $this->service->build(
            Collection::make($employees),
            AttendancePeriod::make('2weeks', now()),
            now(),
        );
    }

    /**
     * @param  array{rows: list<array<string, mixed>>}  $result
     * @return array<string, array<string, mixed>>
     */
    private function cells(array $result): array
    {
        return collect($result['rows'][0]['cells'])->keyBy('date')->all();
    }
}
