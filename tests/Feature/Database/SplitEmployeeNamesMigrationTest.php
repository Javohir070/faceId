<?php

namespace Tests\Feature\Database;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SplitEmployeeNamesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const COLUMNS = ['last_name', 'first_name', 'middle_name'];

    private function migration(): object
    {
        return require database_path('migrations/2026_11_12_120000_add_name_parts_to_employees_table.php');
    }

    public function test_up_adds_name_parts_and_splits_existing_names(): void
    {
        $names = [
            'Valiyev Ali Akmal o‘g‘li',
            'Karimova Dilnoza',
            'Xodim',
            '  Toshmatov   Bekzod  ',
        ];
        $ids = array_map(fn (): int => Employee::factory()->create()->id, $names);

        $migration = $this->migration();
        // Migratsiyadan oldingi holat: ustunlar yo‘q, faqat to‘liq ism bor.
        $migration->down();
        foreach ($ids as $index => $id) {
            DB::table('employees')->where('id', $id)->update(['name' => $names[$index]]);
        }

        $migration->up();

        $rows = DB::table('employees')->whereIn('id', $ids)->orderBy('id')->get(['name', ...self::COLUMNS]);

        $this->assertSame([
            ['Valiyev', 'Ali', 'Akmal o‘g‘li'],
            ['Karimova', 'Dilnoza', null],
            ['Xodim', null, null],
            ['Toshmatov', 'Bekzod', null],
        ], $rows->map(fn ($row): array => [$row->last_name, $row->first_name, $row->middle_name])->all());

        // To‘liq ism tegilmaydi.
        $this->assertSame($names, $rows->pluck('name')->all());
    }

    public function test_down_removes_the_name_parts(): void
    {
        $migration = $this->migration();

        $migration->down();

        foreach (self::COLUMNS as $column) {
            $this->assertFalse(Schema::hasColumn('employees', $column), $column);
        }
        $this->assertTrue(Schema::hasColumn('employees', 'name'));
    }
}
