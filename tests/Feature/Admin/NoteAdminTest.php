<?php

namespace Tests\Feature\Admin;

use App\Enums\NoteType;
use App\Models\Employee;
use App\Models\Note;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class NoteAdminTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    public function test_authorized_user_can_manage_notes(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['notes'],
        ]);
        $employee = Employee::factory()->create(['name' => 'Ali Valiyev']);

        $this->actingAs($admin)
            ->get(route('admin.notes.index', [
                'employee_id' => $employee->id,
                'type' => NoteType::CameLate->value,
                'date_from' => '2026-08-01',
                'date_to' => '2026-08-31',
                'search' => 'transport',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Notes/Index')
                ->where('filters.employee_id', $employee->id)
                ->where('filters.type', NoteType::CameLate->value)
                ->where('filters.date_from', '2026-08-01')
                ->where('filters.date_to', '2026-08-31')
                ->where('filters.search', 'transport')
                ->has('notes.data', 0)
                ->has('employees', 1)
                ->has('types', 3));

        $this->actingAs($admin)
            ->get(route('admin.notes.create', ['employee_id' => $employee->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Notes/Form')
                ->where('prefillEmployeeId', $employee->id)
                ->has('employees', 1)
                ->where('types.0.value', NoteType::CameLate->value)
                ->where('types.0.label', NoteType::CameLate->label()));

        $this->actingAs($admin)
            ->post(route('admin.notes.store'), [
                'employee_id' => $employee->id,
                'type' => NoteType::CameLate->value,
                'dt' => '2026-08-03',
                'note' => 'Transport kechikdi.',
            ])
            ->assertRedirect(route('admin.notes.index'));

        $note = Note::query()->first();
        $this->assertNotNull($note);
        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'employee_id' => $employee->id,
            'type' => NoteType::CameLate->value,
            'note' => 'Transport kechikdi.',
        ]);
        $this->assertSame('2026-08-03', $note->dt->toDateString());

        $this->actingAs($admin)
            ->get(route('admin.notes.edit', $note))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Notes/Form')
                ->where('note.id', $note->id)
                ->has('employees', 1)
                ->has('types', 3));

        $this->actingAs($admin)
            ->put(route('admin.notes.update', $note), [
                'employee_id' => $employee->id,
                'type' => NoteType::LeftEarly->value,
                'dt' => '2026-08-04',
                'note' => 'Ruxsat bilan erta ketdi.',
            ])
            ->assertRedirect(route('admin.notes.index'));

        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'type' => NoteType::LeftEarly->value,
            'note' => 'Ruxsat bilan erta ketdi.',
        ]);
        $this->assertSame('2026-08-04', $note->fresh()->dt->toDateString());

        $this->actingAs($admin)
            ->delete(route('admin.notes.destroy', $note))
            ->assertRedirect(route('admin.notes.index'));

        $this->assertDatabaseMissing('notes', ['id' => $note->id]);
    }

    public function test_unauthorized_user_cannot_access_notes(): void
    {
        $user = $this->createUserWithPermissions([
            config('menu_permissions.home'),
        ]);

        $this->actingAs($user)
            ->get(route('admin.notes.index'))
            ->assertForbidden();
    }

    public function test_duplicate_employee_date_and_type_is_rejected(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['notes'],
        ]);
        $employee = Employee::factory()->create();

        Note::factory()->create([
            'employee_id' => $employee->id,
            'type' => NoteType::Absent,
            'dt' => '2026-08-03',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.notes.create'))
            ->post(route('admin.notes.store'), [
                'employee_id' => $employee->id,
                'type' => NoteType::Absent->value,
                'dt' => '2026-08-03',
                'note' => 'Takroriy izoh.',
            ])
            ->assertRedirect(route('admin.notes.create'))
            ->assertSessionHasErrors('dt');

        $this->assertSame(1, Note::query()->count());
    }
}
