<?php

namespace Tests\Unit;

use App\Services\Reports\AttendanceCalculator;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttendanceCalculatorTest extends TestCase
{
    #[Test]
    public function day_shift_detects_late_entry(): void
    {
        $calc = new AttendanceCalculator;
        $this->assertFalse($calc->isNightShift('09:00', '18:00'));

        $entry = Carbon::parse('2026-08-04 09:15:00');
        $this->assertSame(15 * 60, $calc->lateSeconds($entry, '09:00:00', false));
        $this->assertSame(0, $calc->lateSeconds(Carbon::parse('2026-08-04 08:50:00'), '09:00:00', false));
    }

    #[Test]
    public function night_shift_uses_shifted_clock_for_work_date_and_late(): void
    {
        $calc = new AttendanceCalculator;
        $this->assertTrue($calc->isNightShift('22:00', '06:00'));

        $this->assertSame(
            30 * 60,
            $calc->lateSeconds(Carbon::parse('2026-08-04 22:30:00'), '22:00:00', true),
        );

        $this->assertSame(
            '2026-08-04',
            $calc->workDate(Carbon::parse('2026-08-05 00:30:00'), true),
        );
    }

    #[Test]
    public function early_leave_and_worked_seconds(): void
    {
        $calc = new AttendanceCalculator;
        $this->assertSame(
            30 * 60,
            $calc->earlySeconds(Carbon::parse('2026-08-04 17:30:00'), '18:00:00', false),
        );
        $this->assertSame(
            8 * 3600,
            $calc->workedSeconds(
                Carbon::parse('2026-08-04 09:00:00'),
                Carbon::parse('2026-08-04 17:00:00'),
            ),
        );
        $this->assertSame(0, $calc->workedSeconds(Carbon::parse('2026-08-04 09:00:00'), null));
    }
}
