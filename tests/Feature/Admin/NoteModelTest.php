<?php

namespace Tests\Feature\Admin;

use App\Enums\NoteType;
use App\Models\Employee;
use App\Models\Note;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_note_type_is_cast_to_enum(): void
    {
        $employee = Employee::factory()->create();
        $note = Note::factory()->create([
            'employee_id' => $employee->id,
            'type' => NoteType::CameLate,
            'dt' => '2026-08-03',
            'note' => 'Kechikish sababi',
        ]);

        $note->refresh();

        $this->assertInstanceOf(NoteType::class, $note->type);
        $this->assertSame(NoteType::CameLate, $note->type);
        $this->assertSame('2026-08-03', $note->dt->format('Y-m-d'));
    }

    public function test_duplicate_employee_date_type_violates_unique_constraint(): void
    {
        $employee = Employee::factory()->create();

        Note::factory()->create([
            'employee_id' => $employee->id,
            'type' => NoteType::Absent,
            'dt' => '2026-08-03',
            'note' => 'Kasallik',
        ]);

        $this->expectException(QueryException::class);

        Note::factory()->create([
            'employee_id' => $employee->id,
            'type' => NoteType::Absent,
            'dt' => '2026-08-03',
            'note' => 'Boshqa izoh',
        ]);
    }
}
