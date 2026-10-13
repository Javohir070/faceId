<?php

namespace Tests\Feature\Hikvision;

use App\Models\AccessControllerEvent;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Report;
use App\Services\Hikvision\HikvisionEventPullService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HikvisionEventPullTodayTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_pull_today_imports_only_from_start_of_day_then_from_last_event(): void
    {
        Carbon::setTestNow('2026-10-13 11:00:00');

        $device = Device::factory()->create([
            'ip_address' => '192.168.100.115',
            'port' => 80,
            'type' => Device::TYPE_ENTRY,
            'is_online' => true,
        ]);
        $employee = Employee::factory()->create(['card' => '1003']);

        // Stream orqali kelgan eski (backlog) event boshlanish vaqtiga ta’sir qilmasligi kerak.
        AccessControllerEvent::factory()->create([
            'device_ip' => $device->ip_address,
            'employee_no' => '1003',
            'event_time' => '2026-07-17 12:56:51',
        ]);

        $startTimes = [];

        Http::fake(function (Request $request) use (&$startTimes) {
            $startTimes[] = data_get($request->data(), 'AcsEventCond.startTime');

            return Http::response([
                'AcsEvent' => [
                    'responseStatusStrg' => 'OK',
                    'totalMatches' => 1,
                    'InfoList' => [[
                        'major' => 5,
                        'minor' => 75,
                        'time' => '2026-10-13T10:59:30+05:00',
                        'employeeNoString' => '1003',
                        'name' => $request->url(),
                        'currentVerifyMode' => 'face',
                    ]],
                ],
            ], 200);
        });

        $service = $this->app->make(HikvisionEventPullService::class);

        $first = $service->pullToday($device);
        $second = $service->pullToday($device);

        $this->assertSame(1, $first['imported']);
        $this->assertSame(0, $second['imported']);
        $this->assertSame(1, $second['skipped_duplicate']);

        $this->assertStringStartsWith('2026-10-13T00:00:00', $startTimes[0]);
        $this->assertStringStartsWith('2026-10-13T10:59:25', $startTimes[1]);

        $this->assertSame(1, Report::query()
            ->where('employee_id', $employee->id)
            ->where('device_id', $device->id)
            ->where('occurred_at', '2026-10-13 10:59:30')
            ->count());
    }
}
