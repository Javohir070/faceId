<?php

namespace Tests\Feature\Hikvision;

use App\Models\Device;
use App\Models\Employee;
use App\Models\Report;
use App\Services\Hikvision\HikvisionAlertStreamService;
use App\Services\ReportService;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HikvisionAlertStreamServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_stream_events_become_reports_and_device_goes_online(): void
    {
        $device = Device::factory()->create([
            'ip_address' => '192.168.1.64',
            'port' => 80,
            'user' => 'admin',
            'password' => 'secret',
            'type' => Device::TYPE_ENTRY,
            'is_online' => false,
        ]);
        $employee = Employee::factory()->create(['card' => '1003']);

        $body = implode("\r\n", [
            '--MIME_boundary',
            'Content-Type: application/xml; charset="UTF-8"',
            '',
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<EventNotificationAlert version="2.0">',
            '<ipAddress>192.168.1.64</ipAddress>',
            '<dateTime>2026-07-22T12:00:00+05:00</dateTime>',
            '<AccessControllerEvent>',
            '<employeeNoString>1003</employeeNoString>',
            '<majorEventType>5</majorEventType>',
            '<subEventType>75</subEventType>',
            '</AccessControllerEvent>',
            '</EventNotificationAlert>',
            '--MIME_boundary--',
            '',
        ]);

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'multipart/mixed; boundary=MIME_boundary'], $body),
        ]);
        $received = [];

        $this->streamService($mock)->listen($device, function (array $event) use (&$received): void {
            $received[] = $event;
        });

        // Digest auth curl opsiyasi sifatida uzatiladi — `stream` opsiyasi ishlatilmasligi kerak.
        $options = $mock->getLastOptions();
        $this->assertArrayNotHasKey('stream', $options);
        $this->assertSame(CURLAUTH_DIGEST, $options['curl'][CURLOPT_HTTPAUTH]);

        $this->assertCount(1, $received);
        $this->assertTrue($device->fresh()->is_online);
        $this->assertDatabaseHas('reports', [
            'employee_id' => $employee->id,
            'device_id' => $device->id,
            'type' => Report::TYPE_ENTRY,
        ]);
    }

    private function streamBody(string $reportedIp, string $employeeNo): string
    {
        return implode("\r\n", [
            '--MIME_boundary',
            'Content-Type: application/xml; charset="UTF-8"',
            '',
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<EventNotificationAlert version="2.0">',
            "<ipAddress>{$reportedIp}</ipAddress>",
            '<dateTime>2026-07-22T12:00:00+05:00</dateTime>',
            '<AccessControllerEvent>',
            "<employeeNoString>{$employeeNo}</employeeNoString>",
            '<majorEventType>5</majorEventType>',
            '<subEventType>75</subEventType>',
            '</AccessControllerEvent>',
            '</EventNotificationAlert>',
            '--MIME_boundary--',
            '',
        ]);
    }

    public function test_event_reporting_internal_ip_is_recorded_under_the_connected_device(): void
    {
        // NAT orqasidagi terminal: panelda ochiq IP, event ichida esa o‘zining ichki IP si.
        $device = Device::factory()->create([
            'ip_address' => '203.0.113.5',
            'port' => 8543,
            'user' => 'admin',
            'password' => 'secret',
            'type' => Device::TYPE_ENTRY,
        ]);
        $employee = Employee::factory()->create(['card' => '1003']);

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'multipart/mixed; boundary=MIME_boundary'], $this->streamBody('192.168.1.64', '1003')),
        ]);

        $this->streamService($mock)->listen($device);

        $this->assertDatabaseHas('access_controller_events', ['employee_no' => '1003', 'device_ip' => '203.0.113.5']);
        $this->assertDatabaseMissing('access_controller_events', ['device_ip' => '192.168.1.64']);
        $this->assertDatabaseHas('reports', ['employee_id' => $employee->id, 'device_id' => $device->id]);

        // Xuddi shu o‘tish zaxira tortish orqali ham kelsa, ikkinchi yozuv paydo bo‘lmaydi.
        app(ReportService::class)->createFromWebhookPayload([
            'ipAddress' => '203.0.113.5',
            'dateTime' => '2026-07-22T12:00:00+05:00',
            'AccessControllerEvent' => ['employeeNoString' => '1003', 'majorEventType' => 5, 'subEventType' => 75],
        ], null, $device);

        $this->assertSame(1, Report::query()->count());
    }

    public function test_terminals_behind_one_public_ip_keep_their_own_device_and_type(): void
    {
        $common = ['ip_address' => '203.0.113.5', 'user' => 'admin', 'password' => 'secret'];
        Device::factory()->create([...$common, 'port' => 8001, 'type' => Device::TYPE_ENTRY]);
        $exit = Device::factory()->create([...$common, 'port' => 8002, 'type' => Device::TYPE_EXIT]);
        $employee = Employee::factory()->create(['card' => '1003']);

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'multipart/mixed; boundary=MIME_boundary'], $this->streamBody('192.168.1.65', '1003')),
        ]);

        $this->streamService($mock)->listen($exit);

        $this->assertDatabaseHas('reports', [
            'employee_id' => $employee->id,
            'device_id' => $exit->id,
            'type' => Report::TYPE_EXIT,
        ]);
    }

    public function test_unauthorized_response_throws_and_keeps_device_offline(): void
    {
        $device = Device::factory()->create([
            'ip_address' => '192.168.1.64',
            'port' => 80,
            'user' => 'admin',
            'password' => 'wrong',
            'is_online' => false,
        ]);

        $mock = new MockHandler([new Response(401, [], 'Unauthorized')]);

        try {
            $this->streamService($mock)->listen($device);
            $this->fail('401 javobida exception kutilgan edi.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(401, $exception->getCode());
        }

        $this->assertFalse($device->fresh()->is_online);
        $this->assertDatabaseCount('reports', 0);
    }

    private function streamService(MockHandler $mock): HikvisionAlertStreamService
    {
        return $this->app->make(HikvisionAlertStreamService::class, ['httpHandler' => $mock]);
    }
}
