<?php

namespace Tests\Feature\Console;

use App\Models\Category;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PingDevicesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_ping_command_updates_device_online_status(): void
    {
        Http::fake(['*' => Http::response(['DeviceInfo' => ['deviceName' => 'Terminal']], 200)]);

        $user = User::factory()->create();
        $category = Category::factory()->create();

        $onlineDevice = Device::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'ip_address' => '192.168.1.10',
            'port' => 80,
            'is_online' => false,
        ]);

        $this->artisan('devices:ping')
            ->assertSuccessful()
            ->expectsOutputToContain('Ping yakunlandi');

        $onlineDevice->refresh();
        $this->assertTrue($onlineDevice->is_online);
        $this->assertNotNull($onlineDevice->last_ping_at);
    }

    public function test_scheduled_ping_does_not_probe_alternative_ports(): void
    {
        Http::fake([
            'http://192.168.1.10/ISAPI/*' => Http::response('down', 500),
            '*' => Http::response(['DeviceInfo' => ['deviceName' => 'Terminal']], 200),
        ]);

        $user = User::factory()->create();
        $category = Category::factory()->create();
        $device = Device::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'ip_address' => '192.168.1.10',
            'port' => 80,
            'use_https' => false,
            'is_online' => true,
        ]);

        $this->artisan('devices:ping')->assertSuccessful();

        $device->refresh();
        $this->assertTrue($device->is_online);
        $this->assertSame(80, $device->port);

        Http::assertSentCount(1);
    }

    public function test_device_goes_offline_only_after_failure_threshold(): void
    {
        config(['hikvision.ping_offline_after_failures' => 3]);

        Http::fake(['*' => Http::response('down', 500)]);

        $user = User::factory()->create();
        $category = Category::factory()->create();
        $device = Device::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'ip_address' => '192.168.1.11',
            'port' => 80,
            'is_online' => true,
        ]);

        $service = app(\App\Services\Hikvision\HikvisionDeviceService::class);

        $this->assertFalse($service->refreshOnlineStatus($device));
        $device->refresh();
        $this->assertTrue($device->is_online);

        $this->assertFalse($service->refreshOnlineStatus($device));
        $device->refresh();
        $this->assertTrue($device->is_online);

        $this->assertFalse($service->refreshOnlineStatus($device));
        $device->refresh();
        $this->assertFalse($device->is_online);
        $this->assertNotNull($device->last_ping_at);
    }

    public function test_connect_clears_ping_failure_counter(): void
    {
        config([
            'hikvision.ping_offline_after_failures' => 2,
            'hikvision.webhook_host' => '192.168.100.247',
            'hikvision.webhook_port' => 8000,
            'hikvision.webhook_secret' => 'test-secret',
            'hikvision.webhook_path' => '/api/webhooks/hikvision/events',
        ]);

        Http::fake(['*' => Http::response(['DeviceInfo' => ['deviceName' => 'Terminal']], 200)]);

        $user = User::factory()->create();
        $category = Category::factory()->create();
        $device = Device::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'ip_address' => '192.168.1.12',
            'port' => 80,
            'is_online' => true,
        ]);

        \Illuminate\Support\Facades\Cache::put(
            'hikvision.device.'.$device->id.'.ping_failures',
            1,
            now()->addHours(6),
        );

        $service = app(\App\Services\Hikvision\HikvisionDeviceService::class);
        $service->connect($device);

        Http::fake(['*' => Http::response('down', 500)]);
        $service->refreshOnlineStatus($device->fresh());

        $device->refresh();
        $this->assertTrue($device->is_online, 'Connect xato hisoblagichni tozalashi kerak');
    }
}
