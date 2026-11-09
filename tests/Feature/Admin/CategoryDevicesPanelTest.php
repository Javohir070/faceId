<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

/**
 * Kategoriya belgisi bosilganda o‘ngdan shu kategoriya qurilmalari ochiladi.
 */
class CategoryDevicesPanelTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    private Category $entrances;

    private Device $gate;

    private Device $hall;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entrances = Category::factory()->create(['name' => 'Kirishlar']);
        $other = Category::factory()->create(['name' => 'Boshqa']);
        $owner = User::factory()->create();

        $this->gate = Device::factory()->create([
            'name' => 'Darvoza', 'category_id' => $this->entrances->id, 'user_id' => $owner->id,
            'ip_address' => '10.0.0.5', 'port' => 80, 'type' => Device::TYPE_ENTRY, 'is_online' => true,
        ]);
        $this->hall = Device::factory()->create([
            'name' => 'Zal', 'category_id' => $this->entrances->id, 'user_id' => $owner->id, 'is_online' => false,
        ]);
        Device::factory()->create(['name' => 'Ombor', 'category_id' => $other->id, 'user_id' => $owner->id]);

        $employees = Employee::factory()->count(3)->create();
        $this->gate->employees()->attach($employees[0]->id, ['synced_at' => now()]);
        $this->gate->employees()->attach($employees[1]->id, ['synced_at' => null]);
    }

    public function test_panel_lists_the_devices_of_the_category(): void
    {
        $this->panel($this->createUserWithPermissions(), fn (Assert $reload) => $reload
            ->where('devicesPanel.title', 'Kirishlar')
            ->where('devicesPanel.total', 2)
            ->where('devicesPanel.devices', fn ($rows): bool => collect($rows)->pluck('name')->all() === ['Darvoza', 'Zal'])
            ->where('devicesPanel.devices.0.ip_address', '10.0.0.5')
            ->where('devicesPanel.devices.0.is_online', true)
            ->where('devicesPanel.devices.0.employees_count', 2)
            ->where('devicesPanel.devices.0.synced_employees_count', 1)
            ->where('devicesPanel.devices.1.is_online', false)
            ->where('devicesPanel.filter', ['category_id' => $this->entrances->id]));
    }

    public function test_user_sees_only_their_own_devices_in_the_category(): void
    {
        $user = $this->createDepartmentUserWithPermissions(Department::factory()->create()->id);
        $this->hall->forceFill(['user_id' => $user->id])->save();

        $this->panel($user, fn (Assert $reload) => $reload
            ->where('devicesPanel.total', 1)
            ->where('devicesPanel.devices.0.name', 'Zal'));
    }

    public function test_unknown_key_gives_no_panel(): void
    {
        $this->actingAs($this->createUserWithPermissions())
            ->get(route('admin.categories.index', ['devices' => 'boshqa:1']))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('devicesPanel')
                ->reload(fn (Assert $reload) => $reload->where('devicesPanel', null), only: 'devicesPanel'));
    }

    private function panel(User $user, \Closure $assert): void
    {
        $this->actingAs($user)
            ->get(route('admin.categories.index', ['devices' => 'category:'.$this->entrances->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('devicesPanel')
                ->reload($assert, only: 'devicesPanel'));
    }
}
