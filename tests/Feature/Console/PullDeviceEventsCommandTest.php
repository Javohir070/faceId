<?php

namespace Tests\Feature\Console;

use App\Models\Category;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PullDeviceEventsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_pull_command_imports_access_events_from_device(): void
    {
        Http::fake([
            '*deviceInfo*' => Http::response(['DeviceInfo' => ['deviceName' => 'Test']], 200),
            '*AcsEvent*' => Http::response([
                'AcsEvent' => [
                    'searchID' => 'biocontrol-1',
                    'responseStatusStrg' => 'OK',
                    'numOfMatches' => 1,
                    'InfoList' => [
                        [
                            'major' => 5,
                            'minor' => 75,
                            'time' => '2026-07-21T15:07:56+05:00',
                            'employeeNoString' => '123741',
                            'name' => 'Qayumov Javohir',
                            'currentVerifyMode' => 'face',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $owner = User::factory()->create();
        $category = Category::factory()->create();

        Device::factory()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'ip_address' => '192.168.100.115',
            'port' => 80,
            'type' => Device::TYPE_ENTRY,
            'is_online' => true,
        ]);

        Employee::factory()->create([
            'name' => 'Qayumov Javohir',
            'card' => '123741',
        ]);

        $this->artisan('devices:pull-events')
            ->assertSuccessful();

        $this->assertDatabaseHas('reports', [
            'employee_name' => 'Qayumov Javohir',
            'employee_no' => '123741',
            'type' => Report::TYPE_ENTRY,
        ]);
    }
}
