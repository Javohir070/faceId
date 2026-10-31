<?php

namespace Tests\Feature\Database;

use App\Models\AccessControllerEvent;
use App\Models\Employee;
use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RemoveEventVisitorFeaturesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_31_120000_remove_event_visitor_features.php');
    }

    public function test_up_removes_visitors_their_passes_adjustments_and_permissions(): void
    {
        $migration = $this->migration();
        // Migratsiya oldingi holatni tiklaymiz: ustun, jadval va huquqlar qaytadi.
        $migration->down();

        $staff = Employee::factory()->create(['card' => '1001']);
        $visitor = Employee::factory()->create(['card' => '0000100001']);
        DB::table('employees')->where('id', $visitor->id)->update(['is_visitor' => true]);

        foreach ([$staff, $visitor] as $employee) {
            Report::query()->create([
                'employee_id' => $employee->id,
                'department_id' => $employee->department_id,
                'employee_name' => $employee->name,
                'type' => Report::TYPE_ENTRY,
                'occurred_at' => '2026-10-24 09:00:00',
            ]);
            AccessControllerEvent::query()->create([
                'employee_no' => $employee->card,
                'full_name' => $employee->name,
                'event_time' => '2026-10-24 09:00:00',
            ]);
        }
        DB::table('visit_adjustments')->insert([
            'date' => '2026-10-24', 'entered' => 10, 'male' => 7, 'female' => 3,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $role = Role::findOrCreate('super-admin', 'web');
        $role->givePermissionTo(Permission::query()->whereIn('name', ['Jonli kuzatuv', 'Statistika qo‘shish'])->get());

        $migration->up();

        $this->assertDatabaseMissing('employees', ['id' => $visitor->id]);
        $this->assertDatabaseHas('employees', ['id' => $staff->id]);
        $this->assertSame([$staff->id], Report::query()->pluck('employee_id')->all());
        $this->assertSame(['1001'], AccessControllerEvent::query()->pluck('employee_no')->all());
        $this->assertFalse(Schema::hasColumn('employees', 'is_visitor'));
        $this->assertFalse(Schema::hasTable('visit_adjustments'));
        $this->assertFalse(Permission::query()->whereIn('name', ['Jonli kuzatuv', 'Statistika qo‘shish'])->exists());
    }

    public function test_up_is_safe_when_already_applied(): void
    {
        // RefreshDatabase allaqachon up() ni bajargan — qayta chaqirish xato bermasligi kerak.
        $this->migration()->up();

        $this->assertFalse(Schema::hasColumn('employees', 'is_visitor'));
        $this->assertFalse(Schema::hasTable('visit_adjustments'));
    }
}
