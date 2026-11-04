<?php

namespace Tests\Feature\Admin;

use App\Enums\NoteType;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Group;
use App\Models\Note;
use App\Models\Report;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

/**
 * Bo‘limga biriktirilgan foydalanuvchi faqat o‘z bo‘limi xodimlarini ko‘radi va qo‘shgan xodimi
 * avtomatik o‘z bo‘limiga yoziladi.
 */
class DepartmentIsolationTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    private Department $own;

    private Department $other;

    private Schedule $schedule;

    private Employee $mine;

    private Employee $foreign;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Qurilmaga hech qanday so‘rov ketmasin.
        Http::fake();
        Queue::fake();

        $this->own = Department::factory()->create(['name' => 'Mening Bolimim']);
        $this->other = Department::factory()->create(['name' => 'Begona Bolim']);
        $this->schedule = Schedule::factory()->create(['enter_time' => '09:00', 'exit_time' => '18:00']);

        $this->mine = $this->employee('Mening Xodimim', $this->own, '100001');
        $this->foreign = $this->employee('Begona Xodim', $this->other, '100002');

        $this->user = $this->createDepartmentUserWithPermissions($this->own->id);
    }

    private function employee(string $name, Department $department, string $card): Employee
    {
        return Employee::factory()->create([
            'name' => $name,
            'card' => $card,
            'department_id' => $department->id,
            'schedule_id' => $this->schedule->id,
            'valid_from_dt' => '2026-01-01 00:00:00',
            'valid_to_dt' => '2027-01-01 00:00:00',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function employeePayload(array $overrides = []): array
    {
        return [
            'name' => 'Yangi Xodim',
            'card' => '10000300000000',
            'gender' => 1,
            'schedule_id' => $this->schedule->id,
            'valid_from_dt' => '2026-11-01 09:00:00',
            'valid_to_dt' => '2027-11-01 09:00:00',
            'face' => UploadedFile::fake()->image('face.jpg'),
            ...$overrides,
        ];
    }

    public function test_other_department_employee_never_appears_on_any_page(): void
    {
        Group::factory()->create(['name' => 'Begona Guruh', 'department_id' => $this->other->id]);
        Group::factory()->create(['name' => 'Mening Guruhim', 'department_id' => $this->own->id]);

        foreach ([$this->mine, $this->foreign] as $employee) {
            Report::query()->create([
                'employee_id' => $employee->id,
                'department_id' => $employee->department_id,
                'employee_name' => $employee->name,
                'type' => Report::TYPE_ENTRY,
                'occurred_at' => '2026-10-13 09:30:00',
            ]);
            Note::factory()->create(['employee_id' => $employee->id, 'type' => NoteType::Absent, 'dt' => '2026-10-14']);
        }

        // Bir qurilmada ikkala bo‘lim xodimi ham bor.
        $device = Device::factory()->create(['user_id' => $this->user->id]);
        $device->employees()->attach([
            $this->mine->id => ['synced_at' => now()],
            $this->foreign->id => ['synced_at' => now()],
        ]);

        $range = ['date_from' => '2026-10-01 00:00:00', 'date_to' => '2026-10-31 23:59:00'];
        $urls = [
            route('home.index'),
            route('admin.employees.index'),
            route('admin.employees.index', ['search' => 'Begona']),
            route('admin.employees.index', ['department_ids' => [$this->other->id]]),
            route('admin.employees.create'),
            route('admin.employees.show', $this->mine),
            route('admin.employees.edit', $this->mine),
            route('admin.employees.pull'),
            route('admin.groups.index'),
            route('admin.notes.index'),
            route('admin.notes.create'),
            route('admin.sync.index'),
            route('admin.devices.index'),
            route('admin.devices.show', $device),
            route('admin.attendance.index', ['period' => 'month', 'anchor' => '2026-10-13']),
            route('admin.attendance.index', ['tab' => 'notes']),
            route('admin.attendance.index', ['period' => 'month', 'anchor' => '2026-10-13', 'day_employee_id' => $this->foreign->id, 'day_date' => '2026-10-13']),
            route('admin.reports.pull'),
            ...array_map(
                fn (int $type): string => route('admin.reports.index', [...$range, 'type' => $type]),
                [1, 2, 3, 4, 5, 6, 7],
            ),
            route('admin.reports.index', [...$range, 'type' => 8, 'employee_id' => $this->foreign->id]),
            route('admin.reports.index', [...$range, 'employee_id' => $this->foreign->id]),
        ];

        foreach ($urls as $url) {
            $this->actingAs($this->user)
                ->get($url)
                ->assertOk()
                ->assertDontSee('Begona Xodim', false)
                ->assertDontSee('Begona Bolim', false)
                ->assertDontSee('Begona Guruh', false);
        }

        // Tekshiruv haqiqiy ekanini ko‘rsatadi: o‘z xodimi ko‘rinadi.
        $this->actingAs($this->user)->get(route('admin.employees.index'))->assertSee('Mening Xodimim', false);
        $this->actingAs($this->user)->get(route('admin.devices.show', $device))->assertSee('Mening Xodimim', false);
    }

    public function test_other_department_employee_cannot_be_opened_or_changed_directly(): void
    {
        $params = ['employee' => $this->foreign];

        $this->actingAs($this->user)->get(route('admin.employees.show', $params))->assertNotFound();
        $this->actingAs($this->user)->get(route('admin.employees.edit', $params))->assertNotFound();
        $this->actingAs($this->user)->put(route('admin.employees.update', $params), [])->assertNotFound();
        $this->actingAs($this->user)->delete(route('admin.employees.destroy', $params))->assertNotFound();

        $this->actingAs($this->user)
            ->post(route('admin.employees.bulk-destroy'), ['employee_ids' => [$this->foreign->id]])
            ->assertSessionHasErrors('employee_ids');

        $this->actingAs($this->user)
            ->post(route('admin.attendance.notes.store'), [
                'employee_id' => $this->foreign->id,
                'type' => NoteType::Absent->value,
                'dt' => '2026-11-02',
                'note' => 'Sinov',
            ])
            ->assertSessionHasErrors('employee_id');

        $this->assertModelExists($this->foreign);
        $this->assertSame(0, Note::query()->withoutGlobalScopes()->count());
    }

    public function test_new_employee_always_gets_the_users_department(): void
    {
        $this->actingAs($this->user)
            ->post(route('admin.employees.store'), $this->employeePayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('employees', ['card' => '10000300000000', 'department_id' => $this->own->id]);

        // Boshqa bo‘lim yuborilsa ham o‘z bo‘limiga yoziladi.
        $this->actingAs($this->user)
            ->post(route('admin.employees.store'), $this->employeePayload([
                'card' => '10000400000000',
                'department_id' => $this->other->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('employees', ['card' => '10000400000000', 'department_id' => $this->own->id]);
    }

    public function test_employee_cannot_be_moved_to_another_department(): void
    {
        $this->actingAs($this->user)
            ->put(route('admin.employees.update', $this->mine), [
                'name' => 'Mening Xodimim',
                'card' => $this->mine->card,
                'gender' => 1,
                'schedule_id' => $this->schedule->id,
                'department_id' => $this->other->id,
                'valid_from_dt' => '2026-01-01 00:00:00',
                'valid_to_dt' => '2027-01-01 00:00:00',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->own->id, $this->mine->fresh()->department_id);
    }

    public function test_group_from_another_department_is_rejected(): void
    {
        $foreignGroup = Group::factory()->create(['department_id' => $this->other->id]);

        $this->actingAs($this->user)
            ->post(route('admin.employees.store'), $this->employeePayload(['group_id' => $foreignGroup->id]))
            ->assertSessionHasErrors('group_id');

        $this->assertDatabaseMissing('employees', ['card' => '10000300000000']);
    }

    public function test_user_without_department_gets_a_clear_message_when_adding(): void
    {
        $this->user->update(['department_id' => null]);

        $this->actingAs($this->user->fresh())
            ->post(route('admin.employees.store'), $this->employeePayload(['department_id' => $this->other->id]))
            ->assertSessionHasErrors(['department_id' => 'Sizga bo‘lim biriktirilmagan. Administratorga murojaat qiling.']);

        $this->assertDatabaseMissing('employees', ['card' => '10000300000000']);
    }

    public function test_employee_form_locks_department_for_user_but_not_for_super_admin(): void
    {
        $this->actingAs($this->user)
            ->get(route('admin.employees.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('departmentLocked', true)
                ->has('departments', 1)
                ->where('departments.0.id', $this->own->id));

        $this->actingAs($this->user)
            ->get(route('admin.employees.edit', $this->mine))
            ->assertInertia(fn (Assert $page) => $page->where('departmentLocked', true));

        $this->actingAs($this->createUserWithPermissions())
            ->get(route('admin.employees.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('departmentLocked', false)
                ->has('departments', 2));
    }

    public function test_group_panel_locks_department_for_user_but_not_for_super_admin(): void
    {
        $this->actingAs($this->user)
            ->get(route('admin.groups.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('departmentLocked', true)
                ->has('departments', 1)
                ->where('departments.0.id', $this->own->id));

        $this->actingAs($this->createUserWithPermissions())
            ->get(route('admin.groups.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('departmentLocked', false)
                ->has('departments', 2));
    }

    public function test_new_group_always_gets_the_users_department(): void
    {
        $this->actingAs($this->user)
            ->post(route('admin.groups.store'), ['name' => 'Bolimsiz yuborilgan'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->user)
            ->post(route('admin.groups.store'), ['name' => 'Begonaga yuborilgan', 'department_id' => $this->other->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('groups', ['name' => 'Bolimsiz yuborilgan', 'department_id' => $this->own->id]);
        $this->assertDatabaseHas('groups', ['name' => 'Begonaga yuborilgan', 'department_id' => $this->own->id]);
    }

    public function test_user_without_department_gets_a_clear_message_when_adding_group(): void
    {
        $this->user->update(['department_id' => null]);

        $this->actingAs($this->user->fresh())
            ->post(route('admin.groups.store'), ['name' => 'Sinov', 'department_id' => $this->other->id])
            ->assertSessionHasErrors(['department_id' => 'Sizga bo‘lim biriktirilmagan. Administratorga murojaat qiling.']);

        $this->assertDatabaseMissing('groups', ['name' => 'Sinov']);
    }

    public function test_schedule_used_by_another_department_cannot_be_deleted(): void
    {
        // Umumiy jadvalni bu foydalanuvchi umuman o‘chira olmaydi, shuning uchun o‘z bo‘limi jadvali olinadi.
        $shared = Schedule::factory()->create(['department_id' => $this->own->id]);
        $this->foreign->update(['schedule_id' => $shared->id]);

        $this->actingAs($this->user)
            ->from(route('admin.schedules.index'))
            ->delete(route('admin.schedules.destroy', $shared))
            ->assertRedirect(route('admin.schedules.index'))
            ->assertSessionHas('error');

        $this->assertModelExists($shared);
    }
}
