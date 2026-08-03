<?php

namespace Tests\Feature\Admin;

use App\Enums\NoteType;
use App\Interfaces\NoteRepositoryInterface;
use App\Models\Employee;
use App\Models\Note;
use App\Services\NoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private NoteRepositoryInterface $repository;

    private NoteService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(NoteRepositoryInterface::class);
        $this->service = app(NoteService::class);
    }

    public function test_create_note(): void
    {
        $employee = Employee::factory()->create();

        $note = $this->repository->create([
            'employee_id' => $employee->id,
            'type' => NoteType::CameLate,
            'dt' => '2026-08-03',
            'note' => 'Kechikish sababi',
        ]);

        $this->assertInstanceOf(Note::class, $note);
        $this->assertSame($employee->id, $note->employee_id);
        $this->assertSame(NoteType::CameLate, $note->type);
        $this->assertSame('2026-08-03', $note->dt->format('Y-m-d'));
        $this->assertSame('Kechikish sababi', $note->note);
    }

    public function test_filter_by_employee_id(): void
    {
        $employeeA = Employee::factory()->create(['name' => 'Ali']);
        $employeeB = Employee::factory()->create(['name' => 'Vali']);

        Note::factory()->create(['employee_id' => $employeeA->id, 'dt' => '2026-08-01']);
        Note::factory()->create(['employee_id' => $employeeB->id, 'dt' => '2026-08-02']);

        $results = $this->repository->filteredQuery(['employee_id' => $employeeA->id])->get();

        $this->assertCount(1, $results);
        $this->assertSame($employeeA->id, $results->first()->employee_id);
    }

    public function test_filter_by_type(): void
    {
        $employee = Employee::factory()->create();

        Note::factory()->create([
            'employee_id' => $employee->id,
            'type' => NoteType::CameLate,
            'dt' => '2026-08-01',
        ]);
        Note::factory()->create([
            'employee_id' => $employee->id,
            'type' => NoteType::Absent,
            'dt' => '2026-08-02',
        ]);

        $results = $this->repository->filteredQuery(['type' => NoteType::Absent->value])->get();

        $this->assertCount(1, $results);
        $this->assertSame(NoteType::Absent, $results->first()->type);
    }

    public function test_filter_by_date_range(): void
    {
        $employee = Employee::factory()->create();

        Note::factory()->create(['employee_id' => $employee->id, 'dt' => '2026-08-01']);
        Note::factory()->create(['employee_id' => $employee->id, 'dt' => '2026-08-05']);
        Note::factory()->create(['employee_id' => $employee->id, 'dt' => '2026-08-10']);

        $results = $this->repository->filteredQuery([
            'date_from' => '2026-08-05',
            'date_to' => '2026-08-10',
        ])->get();

        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing(
            ['2026-08-05', '2026-08-10'],
            $results->pluck('dt')->map->format('Y-m-d')->all(),
        );
    }

    public function test_search_by_note_text(): void
    {
        $employee = Employee::factory()->create(['name' => 'Ali']);

        Note::factory()->create([
            'employee_id' => $employee->id,
            'note' => 'Kasallik sababi',
            'dt' => '2026-08-01',
        ]);
        Note::factory()->create([
            'employee_id' => $employee->id,
            'note' => 'Oilaviy sabab',
            'dt' => '2026-08-02',
        ]);

        $results = $this->repository->filteredQuery(['search' => 'Kasallik'])->get();

        $this->assertCount(1, $results);
        $this->assertSame('Kasallik sababi', $results->first()->note);
    }

    public function test_search_by_employee_name(): void
    {
        $employeeA = Employee::factory()->create(['name' => 'Ali Karimov']);
        $employeeB = Employee::factory()->create(['name' => 'Vali Toshmatov']);

        Note::factory()->create(['employee_id' => $employeeA->id, 'dt' => '2026-08-01']);
        Note::factory()->create(['employee_id' => $employeeB->id, 'dt' => '2026-08-02']);

        $results = $this->repository->filteredQuery(['search' => 'Karimov'])->get();

        $this->assertCount(1, $results);
        $this->assertSame($employeeA->id, $results->first()->employee_id);
    }

    public function test_paginate_returns_paginator_with_filters(): void
    {
        $employee = Employee::factory()->create();

        Note::factory()->count(3)->create(['employee_id' => $employee->id]);

        $paginator = $this->repository->paginate(2, ['employee_id' => $employee->id]);

        $this->assertSame(3, $paginator->total());
        $this->assertCount(2, $paginator->items());
    }

    public function test_update_and_delete_note(): void
    {
        $employee = Employee::factory()->create();
        $note = Note::factory()->create([
            'employee_id' => $employee->id,
            'note' => 'Eski izoh',
            'dt' => '2026-08-01',
        ]);

        $updated = $this->repository->update($note, ['note' => 'Yangi izoh']);

        $this->assertSame('Yangi izoh', $updated->note);

        $this->repository->delete($updated);

        $this->assertDatabaseMissing('notes', ['id' => $note->id]);
    }

    public function test_service_delegates_to_repository_and_provides_employees_for_form(): void
    {
        Employee::factory()->create(['name' => 'Zara']);
        Employee::factory()->create(['name' => 'Ali']);

        $note = $this->service->create([
            'employee_id' => Employee::query()->where('name', 'Ali')->value('id'),
            'type' => NoteType::LeftEarly,
            'dt' => '2026-08-03',
            'note' => 'Oilaviy',
        ]);

        $this->assertInstanceOf(Note::class, $note);
        $this->assertSame(NoteType::LeftEarly, $note->type);

        $employees = $this->service->employeesForForm();

        $this->assertSame(['Ali', 'Zara'], $employees->pluck('name')->all());
        $this->assertSame(['id', 'name'], array_keys($employees->first()->getAttributes()));
    }
}
