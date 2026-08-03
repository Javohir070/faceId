<?php

namespace App\Enums;

enum NoteType: int
{
    case CameLate = 1;
    case LeftEarly = 2;
    case Absent = 3;

    public function label(): string
    {
        return match ($this) {
            self::CameLate => 'Kechikdi',
            self::LeftEarly => 'Erta ketdi',
            self::Absent => 'Kelmadi',
        };
    }
}
