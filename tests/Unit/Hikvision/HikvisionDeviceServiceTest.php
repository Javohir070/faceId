<?php

namespace Tests\Unit\Hikvision;

use App\Models\Category;
use App\Models\Device;
use App\Models\User;
use App\Services\Hikvision\HikvisionDeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HikvisionDeviceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_webhook_uses_xml_first(): void
    {
        config([
            'hikvision.webhook_host' => '192.168.100.247',
            'hikvision.webhook_port' => 8000,
            'hikvision.webhook_secret' => 'test-secret',
            'hikvision.webhook_path' => '/api/webhooks/hikvision/events',
        ]);

        Http::fake([
            '*' => Http::response('', 200),
        ]);

        $owner = User::factory()->create();
        $category = Category::factory()->create();
        $device = Device::factory()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
        ]);

        $service = app(HikvisionDeviceService::class);
        $result = $service->setupWebhook($device);

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && str_contains($request->url(), '/ISAPI/Event/notification/httpHosts/1')
                && ! str_contains($request->url(), 'format=json')
                && str_contains((string) $request->header('Content-Type')[0], 'application/xml')
                && str_contains($request->body(), '<HttpHostNotification')
                && str_contains($request->body(), '<ipAddress>192.168.100.247</ipAddress>')
                && str_contains($request->body(), '<portNo>8000</portNo>')
                && str_contains($request->body(), '/api/webhooks/hikvision/events/test-secret');
        });

        Http::assertSentCount(1);
    }

    public function test_setup_webhook_falls_back_to_json_when_xml_fails(): void
    {
        config([
            'hikvision.webhook_host' => '192.168.100.247',
            'hikvision.webhook_port' => 8000,
            'hikvision.webhook_secret' => 'test-secret',
            'hikvision.webhook_path' => '/api/webhooks/hikvision/events',
        ]);

        Http::fake([
            '*' => Http::sequence()
                ->push(['statusCode' => 5, 'subStatusCode' => 'badXmlFormat'], 400)
                ->push('', 200),
        ]);

        $owner = User::factory()->create();
        $category = Category::factory()->create();
        $device = Device::factory()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
        ]);

        $service = app(HikvisionDeviceService::class);
        $result = $service->setupWebhook($device);

        $this->assertTrue($result);
        Http::assertSentCount(2);

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && str_contains($request->url(), 'format=json')
                && is_array($request->data())
                && isset($request->data()['HttpHostNotification']);
        });
    }

    public function test_connect_sets_webhook_configured_only_when_setup_succeeds(): void
    {
        config([
            'hikvision.webhook_host' => '192.168.100.247',
            'hikvision.webhook_port' => 8000,
            'hikvision.webhook_secret' => 'test-secret',
            'hikvision.webhook_path' => '/api/webhooks/hikvision/events',
        ]);

        Http::fake([
            '*' => Http::sequence()
                ->push(['DeviceInfo' => ['deviceName' => 'Terminal']], 200)
                ->push(['DeviceInfo' => ['deviceName' => 'Terminal']], 200)
                ->push(['statusCode' => 5, 'subStatusCode' => 'badXmlFormat'], 400)
                ->push(['statusCode' => 5, 'subStatusCode' => 'badXmlFormat'], 400),
        ]);

        $owner = User::factory()->create();
        $category = Category::factory()->create();
        $device = Device::factory()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'webhook_configured' => true,
        ]);

        $service = app(HikvisionDeviceService::class);
        $service->connect($device);

        $device->refresh();
        $this->assertTrue($device->is_online);
        $this->assertFalse($device->webhook_configured);
    }
}
