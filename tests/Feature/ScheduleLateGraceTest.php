<?php

namespace Tests\Feature;

use App\Enums\AttendanceReportType;
use App\Models\Employee;
use App\Models\Report;
use App\Models\Schedule;
use App\Services\Attendance\AttendanceGridService;
use App\Services\Attendance\AttendancePeriod;
use App\Services\ReportQueryService;
use App\Services\Telegram\TelegramReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

/**
 * Jadvaldagi kechikishga ruxsat: ruxsat ichida kelgan xodim kechikkan emas, ruxsatdan keyin kelganning
 * kechikishi esa kirish vaqtidan hisoblanadi (09:00 + 15 daqiqa, 09:20 → 20 daqiqa).
 */
class ScheduleLateGraceTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    public function test_grace_minutes_are_saved_from_the_drawer_and_shown_back(): void
    {
        $admin = $this->createUserWithPermissions();

        $this->actingAs($admin)
            ->post(route('admin.schedules.store'), [
                'name' => 'Standart',
                'enter_time' => '09:00',
                'exit_time' => '18:00',
                'late_grace_minutes' => 15,
            ])
            ->assertSessionHasNoErrors();

        $schedule = Schedule::query()->where('name', 'Standart')->firstOrFail();
        $this->assertSame(15, $schedule->late_grace_minutes);

        $this->actingAs($admin)
            ->get(route('admin.schedules.index', ['edit' => $schedule->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('drawer.record.late_grace_minutes', 15)
                ->where('schedules.data.0.late_grace_minutes', 15));

        $this->actingAs($admin)
            ->put(route('admin.schedules.update', $schedule), [
                'name' => 'Standart',
                'enter_time' => '09:00',
                'exit_time' => '18:00',
                'late_grace_minutes' => 10,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(10, $schedule->fresh()->late_grace_minutes);
    }

    public function test_empty_grace_is_saved_as_zero(): void
    {
        $admin = $this->createUserWithPermissions();

        $this->actingAs($admin)
            ->post(route('admin.schedules.store'), ['name' => 'Bo‘sh', 'enter_time' => '09:00', 'exit_time' => '18:00'])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)
            ->post(route('admin.schedules.store'), [
                'name' => 'Null',
                'enter_time' => '09:00',
                'exit_time' => '18:00',
                'late_grace_minutes' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame([0, 0], Schedule::query()->orderBy('id')->pluck('late_grace_minutes')->all());
    }

    public function test_grace_outside_range_is_rejected_in_uzbek(): void
    {
        $admin = $this->createUserWithPermissions();
        $base = ['name' => 'Xato', 'enter_time' => '09:00', 'exit_time' => '18:00'];

        $this->actingAs($admin)
            ->post(route('admin.schedules.store'), [...$base, 'late_grace_minutes' => -1])
            ->assertSessionHasErrors(['late_grace_minutes' => 'Kechikishga ruxsat 0 dan 120 daqiqagacha bo‘lsin.']);

        $this->actingAs($admin)
            ->post(route('admin.schedules.store'), [...$base, 'late_grace_minutes' => 121])
            ->assertSessionHasErrors(['late_grace_minutes' => 'Kechikishga ruxsat 0 dan 120 daqiqagacha bo‘lsin.']);

        $this->actingAs($admin)
            ->post(route('admin.schedules.store'), [...$base, 'late_grace_minutes' => 'abc'])
            ->assertSessionHasErrors(['late_grace_minutes' => 'Kechikishga ruxsat butun son bo‘lsin.']);

        $this->assertDatabaseMissing('schedules', ['name' => 'Xato']);
    }

    public function test_late_report_skips_arrivals_within_grace_and_counts_the_rest_from_enter_time(): void
    {
        $schedule = Schedule::factory()->create(['enter_time' => '09:00', 'exit_time' => '18:00', 'late_grace_minutes' => 15]);
        $this->entry($this->employee($schedule, 'Ruxsat ichida'), '2026-08-04 09:15:00');
        $this->entry($this->employee($schedule, 'Ruxsatdan keyin'), '2026-08-04 09:20:00');

        $rows = $this->lateRows('2026-08-04');

        $this->assertSame(['Ruxsatdan keyin'], array_column($rows, 'employee_name'));
        $this->assertSame('00:20:00', $rows[0]['late']);
    }

    public function test_late_report_applies_grace_to_night_shift(): void
    {
        $schedule = Schedule::factory()->create(['enter_time' => '20:00', 'exit_time' => '08:00', 'late_grace_minutes' => 10]);
        $this->entry($this->employee($schedule, 'Tungi ruxsat ichida'), '2026-08-04 20:08:00');
        $this->entry($this->employee($schedule, 'Tungi kechikkan'), '2026-08-04 20:12:00');

        $rows = $this->lateRows('2026-08-04');

        $this->assertSame(['Tungi kechikkan'], array_column($rows, 'employee_name'));
        $this->assertSame('2026-08-04', $rows[0]['date']);
        $this->assertSame('00:12:00', $rows[0]['late']);
    }

    public function test_attendance_grid_marks_arrival_within_grace_as_on_time(): void
    {
        $this->travelTo(Carbon::parse('2026-10-23 12:00:00'));
        $schedule = Schedule::factory()->create(['enter_time' => '09:00', 'exit_time' => '18:00', 'late_grace_minutes' => 15]);
        $employee = $this->employee($schedule, 'Davomat');
        $this->entry($employee, '2026-10-21 09:10:00');
        $this->entry($employee, '2026-10-22 09:20:00');

        $result = app(AttendanceGridService::class)->build(
            Collection::make([$employee]),
            AttendancePeriod::make('2weeks', now()),
            now(),
        );
        $cells = collect($result['rows'][0]['cells'])->keyBy('date');

        $this->assertSame('on_time', $cells['2026-10-21']['status']);
        $this->assertSame('late', $cells['2026-10-22']['status']);
    }

    public function test_telegram_digest_skips_arrivals_within_grace(): void
    {
        $schedule = Schedule::factory()->create(['enter_time' => '09:00', 'exit_time' => '18:00', 'late_grace_minutes' => 15]);
        $this->entry($this->employee($schedule, 'Ruxsat ichida'), '2026-11-10 09:10:00');
        $this->entry($this->employee($schedule, 'Ruxsatdan keyin'), '2026-11-10 09:20:00');

        $digest = app(TelegramReportService::class)->digestForDate(Carbon::parse('2026-11-10'));

        $this->assertSame(['Ruxsatdan keyin'], $digest['late']->pluck('name')->all());
    }

    private function employee(Schedule $schedule, string $name): Employee
    {
        return Employee::factory()->create([
            'name' => $name,
            'schedule_id' => $schedule->id,
            'valid_from_dt' => '2026-01-01 00:00:00',
            'valid_to_dt' => '2027-12-31 00:00:00',
        ]);
    }

    private function entry(Employee $employee, string $at): void
    {
        Report::factory()->forEmployee($employee)->entry()->create(['occurred_at' => $at]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lateRows(string $date): array
    {
        return app(ReportQueryService::class)->paginate([
            'type' => AttendanceReportType::Late->value,
            'date_from' => $date.' 00:00:00',
            'date_to' => $date.' 23:59:59',
        ], 25)->items();
    }
}
