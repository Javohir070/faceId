<?php

namespace Tests\Feature\Hikvision;

use App\Models\AccessControllerEvent;
use App\Models\Device;
use App\Models\Employee;
use App\Services\Hikvision\HikvisionEventPullService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Qurilma (internet) uzilib qayta ulanganda o‘sha oraliqdagi eventlar tortib kelinishi kerak.
 */
class HikvisionEventCatchUpTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $startTimes = [];

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-09 14:05:00'));

        $this->device = Device::factory()->create([
            'ip_address' => '192.168.100.115',
            'port' => 80,
            'type' => Device::TYPE_ENTRY,
            'is_online' => true,
        ]);
        Employee::factory()->create(['card' => '12345678901234']);
    }

    public function test_pull_after_an_outage_starts_where_the_last_complete_pull_ended(): void
    {
        $this->device->forceFill(['events_pulled_until' => '2026-11-09 10:00:00'])->save();

        // Ulanish tiklangach jonli kanal (alertStream/webhook) eng yangi eventni allaqachon yozgan.
        AccessControllerEvent::factory()->create([
            'device_ip' => $this->device->ip_address,
            'employee_no' => '12345678901234',
            'event_time' => '2026-11-09 14:01:00',
        ]);

        $this->fakeDevice(['2026-11-09T11:30:00+05:00', '2026-11-09T14:01:00+05:00']);

        $result = app(HikvisionEventPullService::class)->pullDevice($this->device);

        $this->assertStringStartsWith('2026-11-09T09:55:00', $this->startTimes[0]);
        $this->assertSame(1, $result['imported']);
        $this->assertDatabaseHas('access_controller_events', ['event_time' => '2026-11-09 11:30:00']);
        $this->assertSame('2026-11-09 14:05:00', $this->device->fresh()->events_pulled_until->format('Y-m-d H:i:s'));
    }

    public function test_device_clock_in_another_timezone_is_stored_in_tashkent_time(): void
    {
        $this->device->forceFill(['events_pulled_until' => '2026-11-09 10:00:00'])->save();
        $this->fakeDevice([], '2026-11-09T17:05:00+08:00');

        app(HikvisionEventPullService::class)->pullDevice($this->device);

        $this->assertSame('2026-11-09 14:05:00', $this->device->fresh()->events_pulled_until->format('Y-m-d H:i:s'));
    }

    public function test_failed_pull_keeps_the_mark_so_the_gap_is_pulled_next_time(): void
    {
        $this->device->forceFill(['events_pulled_until' => '2026-11-09 10:00:00'])->save();

        Http::fake([
            '*deviceInfo*' => Http::response(['DeviceInfo' => ['localTime' => '2026-11-09T14:05:00+05:00']], 200),
            '*AcsEvent*' => Http::response('xato', 500),
        ]);

        try {
            app(HikvisionEventPullService::class)->pullDevice($this->device);
        } catch (\RuntimeException) {
            // Kutilgan: qurilma javob bermadi.
        }

        $this->assertSame('2026-11-09 10:00:00', $this->device->fresh()->events_pulled_until->format('Y-m-d H:i:s'));
    }

    public function test_first_pull_looks_back_three_days(): void
    {
        $this->fakeDevice([]);

        app(HikvisionEventPullService::class)->pullDevice($this->device);

        $this->assertStringStartsWith('2026-11-06T14:05:00', $this->startTimes[0]);
    }

    public function test_offline_device_is_caught_up_once_it_is_online_again(): void
    {
        $this->device->forceFill(['events_pulled_until' => '2026-11-09 10:00:00', 'is_online' => false])->save();
        $this->fakeDevice(['2026-11-09T11:30:00+05:00']);

        $service = app(HikvisionEventPullService::class);
        $service->pullAllDevices();
        $this->assertSame([], $this->startTimes);

        $this->device->forceFill(['is_online' => true])->save();
        $service->pullAllDevices();

        $this->assertStringStartsWith('2026-11-09T09:55:00', $this->startTimes[0]);
        $this->assertDatabaseHas('access_controller_events', ['event_time' => '2026-11-09 11:30:00']);
    }

    /**
     * @param  list<string>  $eventTimes
     */
    private function fakeDevice(array $eventTimes, string $deviceNow = '2026-11-09T14:05:00+05:00'): void
    {
        Http::fake(function (Request $request) use ($eventTimes, $deviceNow) {
            if (str_contains($request->url(), 'deviceInfo')) {
                return Http::response(['DeviceInfo' => ['localTime' => $deviceNow]], 200);
            }

            $this->startTimes[] = (string) data_get($request->data(), 'AcsEventCond.startTime');

            return Http::response([
                'AcsEvent' => [
                    'responseStatusStrg' => 'OK',
                    'totalMatches' => count($eventTimes),
                    'InfoList' => array_map(fn (string $time): array => [
                        'major' => 5,
                        'minor' => 75,
                        'time' => $time,
                        'employeeNoString' => '12345678901234',
                        'currentVerifyMode' => 'face',
                    ], $eventTimes),
                ],
            ], 200);
        });
    }
}
