<?php

namespace App\Enums;

enum AttendanceReportType: int
{
    case Entry = 1;
    case Exit = 2;
    case Late = 3;
    case LeftEarly = 4;
    case Present = 5;
    case Absent = 6;
    case WorkTime = 7;
    case Individual = 8;

    public function label(): string
    {
        return match ($this) {
            self::Entry => 'Kirish',
            self::Exit => 'Chiqish',
            self::Late => 'Kechikkan',
            self::LeftEarly => 'Erta ketgan',
            self::Present => 'Kelgan',
            self::Absent => 'Kelmagan',
            self::WorkTime => 'Ish vaqti',
            self::Individual => 'Individual',
        };
    }

    public function requiresSchedule(): bool
    {
        return in_array($this, [self::Late, self::LeftEarly, self::WorkTime, self::Individual], true);
    }

    public function requiresBoundedDates(): bool
    {
        return in_array($this, [self::Present, self::Absent, self::WorkTime], true);
    }
}
