<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\User;
use App\Services\Hikvision\EmployeePullService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class ScheduleOwnershipTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    private Department $own;

    private Department $other;

    private User $admin;

    private User $user;

    private Schedule $general;

    private Schedule $mine;

    private Schedule $foreign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->own = Department::factory()->create(['name' => 'Mening bo‘limim']);
        $this->other = Department::factory()->create(['name' => 'Boshqa bo‘lim']);
        $this->admin = $this->createUserWithPermissions();
        $this->user = $this->createDepartmentUserWithPermissions($this->own->id);

        $this->general = Schedule::factory()->create(['name' => 'Umumiy jadval']);
        $this->mine = Schedule::factory()->create(['name' => 'Mening jadvalim', 'department_id' => $this->own->id]);
        $this->foreign = Schedule::factory()->create(['name' => 'Begona jadval', 'department_id' => $this->other->id]);
    }

    public function test_super_admin_schedule_is_general_even_if_a_department_is_sent(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.schedules.store'), $this->schedulePayload(['department_id' => $this->other->id]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('schedules', ['name' => 'Yangi jadval', 'department_id' => null]);
    }

    public function test_user_schedule_always_belongs_to_their_department(): void
    {
        $this->actingAs($this->user)
            ->post(route('admin.schedules.store'), $this->schedulePayload(['department_id' => $this->other->id]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('schedules', ['name' => 'Yangi jadval', 'department_id' => $this->own->id]);
    }

    public function test_user_without_department_cannot_create_a_schedule(): void
    {
        $this->user->update(['department_id' => null]);

        $this->actingAs($this->user->fresh())
            ->post(route('admin.schedules.store'), $this->schedulePayload())
            ->assertSessionHasErrors(['department_id' => 'Sizga bo‘lim biriktirilmagan. Administratorga murojaat qiling.']);

        $this->assertDatabaseMissing('schedules', ['name' => 'Yangi jadval']);
    }

    public function test_user_sees_general_and_own_schedules_with_correct_rights(): void
    {
        $this->actingAs($this->user)
            ->get(route('admin.schedules.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Schedules/Index')
                ->has('schedules.data', 2));

        $rows = $this->rows($this->user);

        $this->assertFalse($rows['Umumiy jadval']['can_manage']);
        $this->assertNull($rows['Umumiy jadval']['department']);
        $this->assertTrue($rows['Mening jadvalim']['can_manage']);
        $this->assertSame('Mening bo‘limim', $rows['Mening jadvalim']['department']);
        $this->assertArrayNotHasKey('Begona jadval', $rows);
    }

    public function test_super_admin_sees_and_manages_every_schedule(): void
    {
        $rows = $this->rows($this->admin);

        $this->assertCount(3, $rows);
        $this->assertTrue($rows['Umumiy jadval']['can_manage']);
        $this->assertTrue($rows['Mening jadvalim']['can_manage']);
        $this->assertTrue($rows['Begona jadval']['can_manage']);
    }

    public function test_user_cannot_change_or_delete_a_general_schedule(): void
    {
        $this->actingAs($this->user)
            ->put(route('admin.schedules.update', $this->general), $this->schedulePayload())
            ->assertForbidden();

        $this->actingAs($this->user)
            ->delete(route('admin.schedules.destroy', $this->general))
            ->assertForbidden();

        $this->assertDatabaseHas('schedules', ['id' => $this->general->id, 'name' => 'Umumiy jadval']);
    }

    public function test_super_admin_can_change_and_delete_any_schedule(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.schedules.update', $this->mine), $this->schedulePayload())
            ->assertRedirect(route('admin.schedules.index'));

        $this->assertSame('Yangi jadval', $this->mine->fresh()->name);
        $this->assertSame($this->own->id, $this->mine->fresh()->department_id);

        $this->actingAs($this->admin)
            ->delete(route('admin.schedules.destroy', $this->foreign))
            ->assertRedirect(route('admin.schedules.index'));

        $this->assertDatabaseMissing('schedules', ['id' => $this->foreign->id]);
    }

    public function test_second_user_of_the_department_can_change_and_delete_its_schedule(): void
    {
        $colleague = $this->createDepartmentUserWithPermissions($this->own->id);

        $this->actingAs($colleague)
            ->put(route('admin.schedules.update', $this->mine), $this->schedulePayload())
            ->assertRedirect(route('admin.schedules.index'));

        $this->assertSame('Yangi jadval', $this->mine->fresh()->name);

        $this->actingAs($colleague)
            ->delete(route('admin.schedules.destroy', $this->mine))
            ->assertRedirect(route('admin.schedules.index'));

        $this->assertDatabaseMissing('schedules', ['id' => $this->mine->id]);
    }

    public function test_update_cannot_move_a_schedule_to_another_department(): void
    {
        $this->actingAs($this->user)
            ->put(route('admin.schedules.update', $this->mine), $this->schedulePayload(['department_id' => $this->other->id]))
            ->assertRedirect(route('admin.schedules.index'));

        $this->assertSame($this->own->id, $this->mine->fresh()->department_id);
    }

    public function test_schedule_of_another_department_does_not_exist_for_the_user(): void
    {
        $this->actingAs($this->user)
            ->put(route('admin.schedules.update', $this->foreign), $this->schedulePayload())
            ->assertNotFound();

        $this->actingAs($this->user)
            ->delete(route('admin.schedules.destroy', $this->foreign))
            ->assertNotFound();

        $this->assertDatabaseHas('schedules', ['id' => $this->foreign->id, 'name' => 'Begona jadval']);
    }

    public function test_edit_drawer_opens_only_for_a_manageable_schedule(): void
    {
        $this->actingAs($this->user)
            ->get(route('admin.schedules.index', ['edit' => $this->general->id]))
            ->assertInertia(fn (Assert $page) => $page->where('drawer', null));

        $this->actingAs($this->user)
            ->get(route('admin.schedules.index', ['edit' => $this->foreign->id]))
            ->assertInertia(fn (Assert $page) => $page->where('drawer', null));

        $this->actingAs($this->user)
            ->get(route('admin.schedules.index', ['edit' => $this->mine->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('drawer.mode', 'edit')
                ->where('drawer.record.id', $this->mine->id));
    }

    public function test_employee_form_offers_user_only_visible_schedules(): void
    {
        $this->actingAs($this->user)
            ->get(route('admin.employees.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('schedules', 2)
                ->where('schedules', fn ($schedules): bool => collect($schedules)->pluck('name')->sort()->values()->all()
                    === ['Mening jadvalim', 'Umumiy jadval']));
    }

    public function test_employee_form_tells_super_admin_which_department_a_schedule_belongs_to(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.employees.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('schedules', fn ($schedules): bool => collect($schedules)->firstWhere('name', 'Begona jadval')['department_id'] === $this->other->id
                    && collect($schedules)->firstWhere('name', 'Umumiy jadval')['department_id'] === null));
    }

    public function test_user_can_assign_general_or_own_schedule_to_an_employee(): void
    {
        foreach ([$this->general, $this->mine] as $index => $schedule) {
            $this->actingAs($this->user)
                ->post(route('admin.employees.store'), $this->employeePayload($schedule, '1234567890123'.$index))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(2, Employee::query()->withoutGlobalScopes()->count());
    }

    public function test_user_cannot_assign_a_schedule_of_another_department(): void
    {
        $this->actingAs($this->user)
            ->post(route('admin.employees.store'), $this->employeePayload($this->foreign, '12345678901234'))
            ->assertSessionHasErrors(['schedule_id' => 'Jadval tanlangan bo‘limga tegishli emas.']);

        $employee = Employee::factory()->create([
            'department_id' => $this->own->id,
            'schedule_id' => $this->general->id,
        ]);

        $this->actingAs($this->user)
            ->put(route('admin.employees.update', $employee), [
                ...$this->employeePayload($this->foreign, $employee->card),
                'face' => null,
            ])
            ->assertSessionHasErrors('schedule_id');

        $this->assertSame($this->general->id, $employee->fresh()->schedule_id);
    }

    public function test_super_admin_cannot_mix_a_department_schedule_with_another_department(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.employees.store'), [
                ...$this->employeePayload($this->foreign, '12345678901234'),
                'department_id' => $this->own->id,
            ])
            ->assertSessionHasErrors('schedule_id');

        $this->actingAs($this->admin)
            ->post(route('admin.employees.store'), [
                ...$this->employeePayload($this->foreign, '12345678901234'),
                'department_id' => $this->other->id,
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_employee_keeps_showing_a_schedule_the_user_cannot_see(): void
    {
        $employee = Employee::factory()->create([
            'department_id' => $this->own->id,
            'schedule_id' => $this->foreign->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('admin.employees.show', $employee))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('employee.schedule.name', 'Begona jadval'));
    }

    public function test_device_import_uses_a_general_schedule_even_if_a_department_has_the_same_name(): void
    {
        Schedule::factory()->create(['name' => 'Standart', 'department_id' => $this->own->id]);

        Http::fake([
            '*UserInfo/Search*' => Http::response([
                'UserInfoSearch' => [
                    'responseStatusStrg' => 'OK',
                    'UserInfo' => [['employeeNo' => '1001', 'name' => 'Ali Valiyev']],
                ],
            ], 200),
            '*FDSearch*' => Http::response('', 404),
            '*picture*' => Http::response('', 404),
        ]);

        $device = Device::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => Category::factory(),
        ]);

        $this->actingAs($this->user);
        app(EmployeePullService::class)->pullDevice($device);

        $employee = Employee::query()->withoutGlobalScopes()->where('card', '1001')->firstOrFail();

        $this->assertNull($employee->schedule->department_id);
    }

    public function test_deleting_a_department_turns_its_schedules_into_general_ones(): void
    {
        $this->other->delete();

        $this->assertNull($this->foreign->fresh()->department_id);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function rows(User $user): array
    {
        /** @var TestResponse $response */
        $response = $this->actingAs($user)->get(route('admin.schedules.index'));

        return collect($response->inertiaProps('schedules.data'))->keyBy('name')->all();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function schedulePayload(array $overrides = []): array
    {
        return [
            'name' => 'Yangi jadval',
            'enter_time' => '08:00',
            'exit_time' => '17:00',
            ...$overrides,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function employeePayload(Schedule $schedule, string $number): array
    {
        return [
            'last_name' => 'Yangi',
            'first_name' => 'Xodim',
            'card' => $number,
            'gender' => 1,
            'schedule_id' => $schedule->id,
            'department_id' => $this->own->id,
            'valid_from_dt' => '2026-11-01T09:00',
            'valid_to_dt' => '2027-11-01T09:00',
            'face' => UploadedFile::fake()->image('face.jpg'),
        ];
    }
}
