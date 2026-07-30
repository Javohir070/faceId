<?php

namespace Tests\Feature\Console;

use App\Models\Category;
use App\Models\Device;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PullDeviceEmployeesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_pull_employees_command_imports_users_from_online_devices(): void
    {
        Http::fake([
            '*UserInfo/Search*' => Http::response([
                'UserInfoSearch' => [
                    'responseStatusStrg' => 'OK',
                    'UserInfo' => [
                        [
                            'employeeNo' => '7777',
                            'name' => 'Test Xodim',
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

        Device::factory()->create([
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'is_online' => true,
        ]);

        $this->artisan('devices:pull-employees')
            ->assertSuccessful();

        $this->assertDatabaseHas('employees', [
            'card' => '7777',
            'name' => 'Test Xodim',
        ]);
        $this->assertSame(1, Employee::query()->count());
    }
}
