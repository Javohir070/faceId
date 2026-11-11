<?php

namespace Tests\Feature\Legacy;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Face;
use App\Models\Group;
use App\Models\Schedule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Eski Biocontrol bazasidan bir martalik ko‘chirish (legacy:import-employees).
 * Eski baza alohida sqlite ulanishida, eski tizim jadvallari tuzilishida yaratiladi.
 */
class LegacyEmployeeImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-11 10:00');

        config(['database.connections.legacy' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);
        DB::purge('legacy');

        $schema = Schema::connection('legacy');
        $schema->create('departments', function (Blueprint $table): void {
            $table->integer('id');
            $table->string('name');
        });
        $schema->create('groups', function (Blueprint $table): void {
            $table->integer('id');
            $table->string('name');
            $table->integer('parent');
            $table->integer('department');
        });
        $schema->create('schedules', function (Blueprint $table): void {
            $table->integer('id');
            $table->string('name');
            $table->time('enter_time');
            $table->time('exit_time');
        });
        $schema->create('employees', function (Blueprint $table): void {
            $table->integer('id');
            $table->string('name');
            $table->string('card');
            $table->integer('schedule');
            $table->dateTime('valid_from_dt');
            $table->dateTime('valid_to_dt');
            $table->integer('group_id');
            $table->integer('gender');
            $table->string('position');
            $table->integer('department');
        });
        $schema->create('faces', function (Blueprint $table): void {
            $table->integer('employee');
            $table->longText('face');
        });

        $legacy = DB::connection('legacy');
        $legacy->table('departments')->insert([
            ['id' => 5, 'name' => 'Agentlik'],
            ['id' => 6, 'name' => 'Vazirlik'],
            ['id' => 14, 'name' => 'Kasbiy ta\'lim agentligi'],
        ]);
        $legacy->table('groups')->insert([
            // Bo‘lim nomidagi tepa guruh — tashlab yuboriladi.
            ['id' => 322, 'name' => 'Agentlik', 'parent' => 0, 'department' => 5],
            ['id' => 400, 'name' => 'Moliya boshqarmasi', 'parent' => 322, 'department' => 0],
            ['id' => 401, 'name' => 'Buxgalteriya', 'parent' => 400, 'department' => 0],
            // O‘ziga o‘zi ota-ona — ildiz deb olinadi.
            ['id' => 323, 'name' => 'Xalqaro hamkorlik', 'parent' => 323, 'department' => 5],
            // Bo‘limi 0, nomi bo‘lim nomi bilan bir xil ildiz — bo‘lim nomidan topiladi.
            ['id' => 525, 'name' => 'Kasbiy ta\'lim agentligi', 'parent' => 0, 'department' => 0],
            ['id' => 526, 'name' => 'Kadrlar', 'parent' => 525, 'department' => 0],
            ['id' => 321, 'name' => 'Vazirlik', 'parent' => 0, 'department' => 6],
            ['id' => 330, 'name' => 'Devon', 'parent' => 321, 'department' => 0],
        ]);
        $legacy->table('schedules')->insert([
            ['id' => 1, 'name' => 'Day shift', 'enter_time' => '09:00:00', 'exit_time' => '18:00:00'],
        ]);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->legacyEmployee(1, 'Ali', '11111111111111', department: 5, group: 401);

        $this->artisan('legacy:import-employees', ['--connection' => 'legacy', '--departments' => '5', '--dry-run' => true])
            ->expectsOutputToContain('SINOV')
            ->assertSuccessful();

        $this->assertSame(0, Employee::query()->withoutGlobalScopes()->count());
        $this->assertSame(0, Group::query()->withoutGlobalScopes()->count());
        $this->assertSame(0, Department::query()->count());
    }

    public function test_only_selected_departments_with_their_tree_and_faces_are_imported(): void
    {
        $standart = Schedule::query()->withoutGlobalScopes()->create(['name' => 'Standart', 'enter_time' => '09:00', 'exit_time' => '18:00']);

        $this->legacyEmployee(1, 'Ali', '11111111111111', department: 5, group: 401, face: $this->jpegBase64(), gender: 1, position: 'Buxgalter');
        $this->legacyEmployee(2, 'Vali', '22222222222222', department: 5, group: 322); // tepa guruhda — bo‘limning o‘zida
        $this->legacyEmployee(3, 'Gani', '33333333333333', department: 5, group: 330); // guruhi Vazirlikda
        $this->legacyEmployee(4, 'Olib tashlanadigan', '44444444444444', department: 6, group: 330);
        $this->legacyEmployee(5, 'Sobir', '55555555555555', department: 14, group: 526, face: 'buzilgan');

        $this->artisan('legacy:import-employees', ['--connection' => 'legacy', '--departments' => '5,14', '--force' => true])
            ->assertSuccessful();

        $this->assertEqualsCanonicalizing(['Agentlik', 'Kasbiy ta\'lim agentligi'], Department::query()->pluck('name')->all());
        $agentlik = Department::query()->where('name', 'Agentlik')->firstOrFail();
        $kasbiy = Department::query()->where('name', 'Kasbiy ta\'lim agentligi')->firstOrFail();

        // Tepa "Agentlik" guruhi yo‘q, uning bolasi bo‘lim ostida; o‘ziga o‘zi ota-ona guruh ham ildiz bo‘ldi.
        $moliya = Group::query()->withoutGlobalScopes()->where('name', 'Moliya boshqarmasi')->firstOrFail();
        $this->assertSame($agentlik->id, $moliya->department_id);
        $this->assertNull($moliya->parent_id);
        $buxgalteriya = Group::query()->withoutGlobalScopes()->where('name', 'Buxgalteriya')->firstOrFail();
        $this->assertSame($moliya->id, $buxgalteriya->parent_id);
        $this->assertNull(Group::query()->withoutGlobalScopes()->where('name', 'Xalqaro hamkorlik')->value('parent_id'));
        $this->assertFalse(Group::query()->withoutGlobalScopes()->whereIn('name', ['Agentlik', 'Kasbiy ta\'lim agentligi', 'Vazirlik', 'Devon'])->exists());
        $this->assertSame($kasbiy->id, Group::query()->withoutGlobalScopes()->where('name', 'Kadrlar')->value('department_id'));

        $ali = Employee::query()->withoutGlobalScopes()->where('card', '11111111111111')->firstOrFail();
        $this->assertSame($agentlik->id, $ali->department_id);
        $this->assertSame($buxgalteriya->id, $ali->group_id);
        $this->assertSame($standart->id, $ali->schedule_id);
        $this->assertSame(Employee::GENDER_MALE, $ali->gender);
        $this->assertSame('Buxgalter', $ali->position);
        $this->assertTrue(Face::query()->where('employee_id', $ali->id)->exists());

        $this->assertNull(Employee::query()->withoutGlobalScopes()->where('card', '22222222222222')->value('group_id'));
        $gani = Employee::query()->withoutGlobalScopes()->where('card', '33333333333333')->firstOrFail();
        $this->assertSame($agentlik->id, $gani->department_id);
        $this->assertNull($gani->group_id);

        $this->assertFalse(Employee::query()->withoutGlobalScopes()->where('card', '44444444444444')->exists());

        // Buzilgan rasm yozilmaydi, xodimning o‘zi esa ko‘chadi.
        $sobir = Employee::query()->withoutGlobalScopes()->where('card', '55555555555555')->firstOrFail();
        $this->assertFalse(Face::query()->where('employee_id', $sobir->id)->exists());

        $this->assertSame(1, Schedule::query()->withoutGlobalScopes()->count());
    }

    public function test_existing_employee_with_same_jshshir_is_updated_and_keeps_its_face(): void
    {
        $pulled = Department::query()->create(['name' => 'Qurilmadan']);
        $existing = Employee::factory()->create([
            'card' => '11111111111111',
            'name' => 'Terminaldagi ism',
            'department_id' => $pulled->id,
            'position' => 'Eski lavozim',
            'gender' => null,
        ]);
        Face::query()->create(['employee_id' => $existing->id, 'face' => 'mavjud-rasm']);

        $this->legacyEmployee(1, 'Ali Valiyev', '11111111111111', department: 5, group: 401, face: $this->jpegBase64(), gender: 2);

        $this->artisan('legacy:import-employees', ['--connection' => 'legacy', '--departments' => '5', '--force' => true])
            ->assertSuccessful();

        $this->assertSame(1, Employee::query()->withoutGlobalScopes()->count());
        $existing->refresh();
        $this->assertSame('Ali Valiyev', $existing->name);
        $this->assertSame(Department::query()->where('name', 'Agentlik')->value('id'), $existing->department_id);
        $this->assertSame(Group::query()->withoutGlobalScopes()->where('name', 'Buxgalteriya')->value('id'), $existing->group_id);
        $this->assertSame('Eski lavozim', $existing->position, 'Eski tizimda lavozim bo‘sh — mavjudi o‘chmasin');
        $this->assertSame(Employee::GENDER_FEMALE, $existing->gender);
        $this->assertSame('mavjud-rasm', Face::query()->where('employee_id', $existing->id)->value('face'));
    }

    public function test_already_expired_employees_do_not_trigger_old_telegram_alerts(): void
    {
        $this->legacyEmployee(1, 'Muddati o‘tgan', '11111111111111', department: 5, group: 0, validTo: '2026-06-01 18:00:00');
        $this->legacyEmployee(2, 'Faol', '22222222222222', department: 5, group: 0, validTo: '2027-06-01 18:00:00');

        $this->artisan('legacy:import-employees', ['--connection' => 'legacy', '--departments' => '5', '--force' => true])
            ->assertSuccessful();

        $this->assertNotNull(Employee::query()->withoutGlobalScopes()->where('card', '11111111111111')->value('expiry_notified_at'));
        $this->assertNull(Employee::query()->withoutGlobalScopes()->where('card', '22222222222222')->value('expiry_notified_at'));
    }

    public function test_running_twice_creates_no_duplicates(): void
    {
        $this->legacyEmployee(1, 'Ali', '11111111111111', department: 5, group: 401, face: $this->jpegBase64());

        foreach ([1, 2] as $run) {
            $this->artisan('legacy:import-employees', ['--connection' => 'legacy', '--departments' => '5', '--force' => true])
                ->assertSuccessful();
        }

        $this->assertSame(1, Department::query()->count());
        $this->assertSame(3, Group::query()->withoutGlobalScopes()->count());
        $this->assertSame(1, Employee::query()->withoutGlobalScopes()->count());
        $this->assertSame(1, Face::query()->count());
    }

    public function test_unknown_department_is_rejected(): void
    {
        $this->artisan('legacy:import-employees', ['--connection' => 'legacy', '--departments' => '5,99', '--force' => true])
            ->expectsOutputToContain('99')
            ->assertFailed();

        $this->assertSame(0, Department::query()->count());
    }

    private function legacyEmployee(
        int $id,
        string $name,
        string $card,
        int $department,
        int $group,
        ?string $face = null,
        int $gender = 0,
        string $position = '',
        string $validTo = '2027-12-31 18:00:00',
    ): void {
        DB::connection('legacy')->table('employees')->insert([
            'id' => $id,
            'name' => $name,
            'card' => $card,
            'schedule' => 1,
            'valid_from_dt' => '2025-01-01 09:00:00',
            'valid_to_dt' => $validTo,
            'group_id' => $group,
            'gender' => $gender,
            'position' => $position,
            'department' => $department,
        ]);

        if ($face !== null) {
            DB::connection('legacy')->table('faces')->insert(['employee' => $id, 'face' => $face]);
        }
    }

    private function jpegBase64(): string
    {
        $image = imagecreatetruecolor(8, 8);
        ob_start();
        imagejpeg($image);

        return base64_encode((string) ob_get_clean());
    }
}
