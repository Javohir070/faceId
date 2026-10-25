<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class EmployeePullPageTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    public function test_authorized_user_can_view_employee_pull_page(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['employees-pull'],
        ]);

        Device::factory()->create(['name' => 'Terminal 1']);

        $this->actingAs($admin)
            ->get(route('admin.employees.pull'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Employees/Pull')
                ->has('devices', 1)
                ->where('devices.0.name', 'Terminal 1'));
    }

    public function test_pull_store_imports_employees_without_duplicates(): void
    {
        Http::fake([
            '*UserInfo/Search*' => Http::response([
                'UserInfoSearch' => [
                    'responseStatusStrg' => 'OK',
                    'UserInfo' => [
                        [
                            'employeeNo' => '44001',
                            'name' => 'Admin Pull User',
                            'Valid' => [
                                'beginTime' => '2026-01-01T00:00:00',
                                'endTime' => '2030-12-31T23:59:59',
                            ],
                        ],
                    ],
                ],
            ], 200),
            '*FDSearch*' => Http::response('', 404),
            '*picture*' => Http::response('', 404),
        ]);

        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['employees-pull'],
        ]);

        $device = Device::factory()->create([
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'is_online' => true,
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($admin)
            ->post(route('admin.employees.pull.store'), [
                'device_ids' => [$device->id],
            ])
            ->assertRedirect(route('admin.employees.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('employees', [
            'card' => '44001',
            'name' => 'Admin Pull User',
        ]);
        $this->assertSame(1, Employee::query()->count());
        $this->assertTrue($device->employees()->where('card', '44001')->exists());
    }

    public function test_scoped_user_pull_skips_other_department_card_and_imports_into_own_department(): void
    {
        Http::fake([
            '*UserInfo/Search*' => Http::response([
                'UserInfoSearch' => [
                    'responseStatusStrg' => 'OK',
                    'UserInfo' => [
                        [
                            'employeeNo' => '55001',
                            'name' => 'Qurilmadagi ism',
                            'Valid' => [
                                'beginTime' => '2026-01-01T00:00:00',
                                'endTime' => '2030-12-31T23:59:59',
                            ],
                        ],
                        [
                            'employeeNo' => '55002',
                            'name' => 'Yangi Xodim',
                            'Valid' => [
                                'beginTime' => '2026-01-01T00:00:00',
                                'endTime' => '2030-12-31T23:59:59',
                            ],
                        ],
                    ],
                ],
            ], 200),
            '*FDSearch*' => Http::response('', 404),
            '*picture*' => Http::response('', 404),
        ]);

        $own = Department::factory()->create();
        $other = Department::factory()->create();
        $otherEmployee = Employee::factory()->create([
            'card' => '55001',
            'name' => 'Boshqa bo‘lim xodimi',
            'department_id' => $other->id,
        ]);

        $user = $this->createDepartmentUserWithPermissions($own->id, [
            config('menu_permissions.resources')['employees-pull'],
        ]);

        $device = Device::factory()->create([
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'is_online' => true,
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($user)
            ->post(route('admin.employees.pull.store'), [
                'device_ids' => [$device->id],
            ])
            ->assertRedirect(route('admin.employees.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('employees', [
            'id' => $otherEmployee->id,
            'name' => 'Boshqa bo‘lim xodimi',
            'department_id' => $other->id,
        ]);
        $this->assertDatabaseMissing('device_employee', ['employee_id' => $otherEmployee->id]);
        $this->assertDatabaseHas('employees', [
            'card' => '55002',
            'department_id' => $own->id,
        ]);
        $this->assertDatabaseCount('employees', 2);
    }

    public function test_unauthorized_user_cannot_access_employee_pull(): void
    {
        $user = $this->createUserWithPermissions([
            config('menu_permissions.home'),
        ]);

        $this->actingAs($user)
            ->get(route('admin.employees.pull'))
            ->assertForbidden();
    }
}
