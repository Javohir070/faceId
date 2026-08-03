<?php

namespace Tests\Unit;

use App\Enums\NoteType;
use Tests\TestCase;

class NoteTypeTest extends TestCase
{
    public function test_labels_and_values(): void
    {
        $this->assertSame(1, NoteType::CameLate->value);
        $this->assertSame(2, NoteType::LeftEarly->value);
        $this->assertSame(3, NoteType::Absent->value);
        $this->assertSame('Kechikdi', NoteType::CameLate->label());
        $this->assertSame('Erta ketdi', NoteType::LeftEarly->label());
        $this->assertSame('Kelmadi', NoteType::Absent->label());
    }
}
