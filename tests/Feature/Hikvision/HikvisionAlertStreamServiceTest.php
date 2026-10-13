<?php

namespace Tests\Feature\Hikvision;

use App\Models\Device;
use App\Models\Employee;
use App\Models\Report;
use App\Services\Hikvision\HikvisionAlertStreamService;
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
