<?php

namespace Tests\Unit\Attendance;

use App\Services\Attendance\AttendancePeriod;
use Carbon\Carbon;
use Tests\TestCase;

class AttendancePeriodTest extends TestCase
{
    public function test_two_weeks_period_covers_previous_and_current_week(): void
    {
        $p = AttendancePeriod::make('2weeks', Carbon::parse('2026-10-23'));

        $this->assertSame('2026-10-12', $p->start->toDateString());
        $this->assertSame('2026-10-25', $p->end->toDateString());
        $this->assertCount(10, $p->workDates());
        $this->assertSame(['2026-10-12', '2026-10-16'], [$p->workDates()[0], $p->workDates()[4]]);
        $this->assertNotContains('2026-10-17', $p->workDates());
        $this->assertSame('12.10 – 23.10.2026', $p->label());
        $this->assertSame('2026-10-09', $p->previousAnchor());
        $this->assertSame('2026-11-06', $p->nextAnchor());
    }

    public function test_week_and_month_periods(): void
    {
        $w = AttendancePeriod::make('week', Carbon::parse('2026-10-21'));
        $this->assertSame(['2026-10-19', '2026-10-25'], [$w->start->toDateString(), $w->end->toDateString()]);
        $this->assertSame('2026-10-14', $w->previousAnchor());

        $m = AttendancePeriod::make('month', Carbon::parse('2026-10-23'));
        $this->assertSame(['2026-10-01', '2026-10-31'], [$m->start->toDateString(), $m->end->toDateString()]);
        $this->assertCount(22, $m->workDates());
        $this->assertSame('2026-09-01', $m->previousAnchor());
    }

    public function test_month_navigation_crosses_year(): void
    {
        $m = AttendancePeriod::make('month', Carbon::parse('2026-12-15'));
        $this->assertSame('2027-01-01', $m->nextAnchor());

        $two = AttendancePeriod::make('2weeks', Carbon::parse('2027-01-02')); // shanba
        $this->assertSame('21.12.2026 – 01.01.2027', $two->label());
    }

    public function test_columns_mark_today_and_weekday_names(): void
    {
        $cols = AttendancePeriod::make('2weeks', Carbon::parse('2026-10-23'))
            ->columns(Carbon::parse('2026-10-23 12:00'));

        $this->assertSame(['date' => '2026-10-12', 'label' => '12.10', 'weekday' => 'Du', 'is_today' => false], $cols[0]);
        $this->assertSame(['Du', 'Se', 'Cho', 'Pa', 'Ju'], array_column(array_slice($cols, 0, 5), 'weekday'));
        $this->assertTrue($cols[9]['is_today']);
    }

    public function test_unknown_type_falls_back_to_two_weeks(): void
    {
        $this->assertSame('2weeks', AttendancePeriod::make('year', Carbon::parse('2026-10-23'))->type);
    }

    public function test_to_array_exposes_navigation(): void
    {
        $data = AttendancePeriod::make('week', Carbon::parse('2026-10-21'))->toArray();

        $this->assertSame([
            'type' => 'week',
            'date' => '2026-10-21',
            'label' => '19.10 – 23.10.2026',
            'prev' => '2026-10-14',
            'next' => '2026-10-28',
            'start' => '2026-10-19',
            'end' => '2026-10-23',
        ], $data);
    }
}
