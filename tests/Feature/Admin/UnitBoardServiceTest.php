<?php

namespace Tests\Feature\Admin;

use App\Enums\NoteType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Group;
use App\Models\Note;
use App\Models\Report;
use App\Models\Schedule;
use App\Services\Units\UnitBoardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

/**
 * Tarkibiy bo‘linmalar: bugungi holatlar va kartochkalar tartibi.
 * Qoidalar: docs/superpowers/specs/2026-11-12-structural-units-design.md
 */
class UnitBoardServiceTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->createUserWithPermissions());
        $this->department = Department::factory()->create(['name' => 'Agentlik']);
    }

    public function test_statuses_for_today(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 12:00:00'));

        $inside = $this->employee(['name' => 'A Ichkarida']);
        $this->punch($inside, '2026-11-11 08:52', 'in');

        $left = $this->employee(['name' => 'B Chiqqan']);
        $this->punch($left, '2026-11-11 08:00', 'in');
        $this->punch($left, '2026-11-11 11:00', 'out');

        $absent = $this->employee(['name' => 'C Kelmagan']);

        $excused = $this->employee(['name' => 'D Sababli']);
        Note::factory()->create(['employee_id' => $excused->id, 'type' => NoteType::Absent, 'dt' => '2026-11-11', 'note' => 'Kasal']);

        $board = $this->build();

        $this->assertSame(['total' => 4, 'inside' => 1, 'outside' => 3, 'excused' => 1, 'unexcused' => 1], $board['counts']);

        $this->assertMatches(['inside' => true, 'in' => '08:52', 'out' => null, 'unexcused' => false], $this->find($board, $inside));
        $this->assertMatches(['inside' => false, 'in' => '08:00', 'out' => '11:00', 'unexcused' => false], $this->find($board, $left));
        $this->assertMatches(['inside' => false, 'in' => null, 'excused' => false, 'unexcused' => true], $this->find($board, $absent));
        $this->assertMatches(['inside' => false, 'excused' => true, 'unexcused' => false, 'notes' => [['type' => 'Kelmadi', 'text' => 'Kasal']]], $this->find($board, $excused));
    }

    public function test_weekend_has_no_unexcused(): void
    {
        $this->travelTo(Carbon::parse('2026-11-14 12:00:00'));
        $employee = $this->employee();

        $board = $this->build();

        $this->assertSame(0, $board['counts']['unexcused']);
        $this->assertSame(1, $board['counts']['outside']);
        $this->assertFalse($this->find($board, $employee)['unexcused']);
    }

    public function test_night_shift_employee_stays_inside_after_midnight(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 02:00:00'));
        $night = Schedule::factory()->create(['enter_time' => '20:00', 'exit_time' => '08:00']);
        $employee = $this->employee(['schedule_id' => $night->id]);
        $this->punch($employee, '2026-11-11 20:05', 'in');

        $row = $this->find($this->build(), $employee);

        $this->assertTrue($row['inside']);
        $this->assertSame('20:05', $row['in']);
    }

    public function test_yesterdays_unclosed_entry_does_not_count_today(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 12:00:00'));
        $employee = $this->employee();
        $this->punch($employee, '2026-11-10 09:00', 'in');

        $this->assertMatches(['inside' => false, 'in' => null, 'unexcused' => true], $this->find($this->build(), $employee));
    }

    public function test_yesterdays_note_does_not_excuse_today(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 12:00:00'));
        $employee = $this->employee();
        Note::factory()->create(['employee_id' => $employee->id, 'type' => NoteType::Absent, 'dt' => '2026-11-10']);

        $this->assertMatches(['excused' => false, 'unexcused' => true, 'notes' => []], $this->find($this->build(), $employee));
    }

    public function test_entry_from_terminal_with_fast_clock_counts(): void
    {
        // Terminal soati serverdan 2 daqiqa oldinda: Davomat bu o‘tishni bugunga yozadi, bu sahifa ham.
        $this->travelTo(Carbon::parse('2026-11-11 12:00:00'));
        $employee = $this->employee();
        $this->punch($employee, '2026-11-11 12:02', 'in');

        $this->assertMatches(['inside' => true, 'in' => '12:02', 'unexcused' => false], $this->find($this->build(), $employee));
    }

    public function test_employee_query_never_loads_card(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 12:00:00'));
        $this->employee()->face()->create(['face' => base64_encode('rasm')]);

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        app(UnitBoardService::class)->build(now(), true);

        $employeeQueries = array_values(array_filter($queries, fn (string $sql): bool => preg_match('/from\s+["`]?employees["`]?\s/i', $sql) === 1));
        $this->assertNotEmpty($employeeQueries);
        foreach ($employeeQueries as $sql) {
            $this->assertDoesNotMatchRegularExpression('/employees["`]?\.\*/i', $sql);
            $this->assertStringNotContainsString('card', $sql);
        }
    }

    public function test_expired_and_upcoming_employees_are_hidden(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 12:00:00'));
        $this->employee(['valid_from_dt' => '2026-01-01 00:00:00', 'valid_to_dt' => '2026-11-01 00:00:00']);
        $this->employee(['valid_from_dt' => '2026-12-01 00:00:00', 'valid_to_dt' => '2027-12-01 00:00:00']);

        $board = $this->build();

        $this->assertSame(0, $board['counts']['total']);
        $this->assertSame([], $board['units']);
    }

    public function test_units_follow_group_tree_with_ungrouped_last(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 12:00:00'));

        $works = Group::factory()->create(['name' => 'Ishlar bo‘limi', 'department_id' => $this->department->id]);
        $technical = Group::factory()->childOf($works)->create(['name' => 'Texnik xodimlar']);
        $office = Group::factory()->create(['name' => 'Devonxona', 'department_id' => $this->department->id]);
        Group::factory()->create(['name' => 'Fond', 'department_id' => $this->department->id]);

        $this->employee(['group_id' => $works->id]);
        $this->employee(['group_id' => $technical->id]);
        $this->employee(['group_id' => $office->id]);
        $this->employee(['group_id' => null]);

        $bureau = Department::factory()->create(['name' => 'Byuro']);
        $this->employee(['department_id' => $bureau->id]);

        $units = $this->build()['units'];

        $this->assertSame(
            ['Devonxona', 'Ishlar bo‘limi', 'Ishlar bo‘limi › Texnik xodimlar', 'Guruhsiz', 'Guruhsiz'],
            array_column($units, 'title'),
        );
        $this->assertSame(['Agentlik', 'Agentlik', 'Agentlik', 'Agentlik', 'Byuro'], array_column($units, 'department'));
        $this->assertSame('group:'.$technical->id, $units[2]['key']);
        $this->assertSame('ungrouped:'.$this->department->id, $units[3]['key']);
        $this->assertSame('ungrouped:'.$bureau->id, $units[4]['key']);
        $this->assertSame('Ishlar bo‘limi › Texnik xodimlar', $units[2]['employees'][0]['group']);
        $this->assertNull($units[3]['employees'][0]['group']);
    }

    public function test_inside_employees_come_first_in_card(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 12:00:00'));
        $group = Group::factory()->create(['department_id' => $this->department->id]);
        $this->employee(['name' => 'Aziz', 'group_id' => $group->id]);
        $zafar = $this->employee(['name' => 'Zafar', 'group_id' => $group->id]);
        $this->punch($zafar, '2026-11-11 09:00', 'in');

        $unit = $this->build()['units'][0];

        $this->assertSame(['Zafar', 'Aziz'], array_column($unit['employees'], 'name'));
        $this->assertSame(2, $unit['total']);
        $this->assertSame(1, $unit['inside']);
    }

    public function test_face_url_only_when_requested(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 12:00:00'));
        $employee = $this->employee();
        $employee->face()->create(['face' => base64_encode('rasm')]);

        $service = app(UnitBoardService::class);

        $this->assertStringContainsString('/face?v=', (string) $this->find($service->build(now(), true), $employee)['face_url']);
        $this->assertNull($this->find($service->build(now(), false), $employee)['face_url']);
    }

    /**
     * @return array<string, mixed>
     */
    private function build(): array
    {
        return app(UnitBoardService::class)->build(now(), false);
    }

    /**
     * @param  array<string, mixed>  $board
     * @return array<string, mixed>
     */
    private function find(array $board, Employee $employee): array
    {
        foreach ($board['units'] as $unit) {
            foreach ($unit['employees'] as $row) {
                if ($row['id'] === $employee->id) {
                    return $row;
                }
            }
        }

        $this->fail("Xodim #{$employee->id} kartochkalarda yo‘q");
    }

    /**
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $row
     */
    private function assertMatches(array $expected, array $row): void
    {
        $actual = [];
        foreach (array_keys($expected) as $key) {
            $this->assertArrayHasKey($key, $row);
            $actual[$key] = $row[$key];
        }

        $this->assertSame($expected, $actual);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function employee(array $attributes = []): Employee
    {
        return Employee::factory()->create(array_merge([
            'department_id' => $this->department->id,
            'schedule_id' => Schedule::factory()->create(['enter_time' => '09:00', 'exit_time' => '18:00'])->id,
            'valid_from_dt' => '2026-01-01 00:00:00',
            'valid_to_dt' => '2027-12-31 00:00:00',
        ], $attributes));
    }

    private function punch(Employee $employee, string $at, string $direction): void
    {
        Report::query()->create([
            'employee_id' => $employee->id,
            'department_id' => $employee->department_id,
            'employee_name' => $employee->name,
            'type' => $direction === 'in' ? Report::TYPE_ENTRY : Report::TYPE_EXIT,
            'occurred_at' => $at,
        ]);
    }
}
