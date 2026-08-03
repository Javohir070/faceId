<?php

namespace Tests\Feature\Telegram;

use App\Models\Category;
use App\Models\Device;
use App\Models\User;
use App\Services\Hikvision\HikvisionDeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTelegramAdmin;
use Tests\TestCase;

class DeviceOfflineTelegramAlertTest extends TestCase
{
    use CreatesTelegramAdmin;
    use RefreshDatabase;

    public function test_notifies_once_when_device_transitions_to_offline(): void
    {
        config([
            'telegram.token' => 'test-token',
            'hikvision.ping_offline_after_failures' => 1,
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
            '*' => Http::response('down', 500),
        ]);

        $this->createTelegramSuperAdmin(['telegram_chat_id' => '111']);
        $owner = User::factory()->create();
        $category = Category::factory()->create();
        $device = Device::factory()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'name' => 'Kirish-1',
            'ip_address' => '192.168.1.50',
            'port' => 80,
            'is_online' => true,
        ]);

        $service = app(HikvisionDeviceService::class);
        $service->refreshOnlineStatus($device);

        $device->refresh();
        $this->assertFalse($device->is_online);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains((string) $request['text'], 'Kirish-1')
                && str_contains((string) $request['text'], 'ishlamayapti');
        });

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
            '*' => Http::response('down', 500),
        ]);

        $service->refreshOnlineStatus($device->fresh());

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'sendMessage'));
    }

    public function test_notifies_when_device_comes_back_online(): void
    {
        config(['telegram.token' => 'test-token']);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
            '*' => Http::response(['DeviceInfo' => ['deviceName' => 'Terminal']], 200),
        ]);

        $this->createTelegramSuperAdmin(['telegram_chat_id' => '111']);
        $owner = User::factory()->create();
        $category = Category::factory()->create();
        $device = Device::factory()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'name' => 'Chiqish-1',
            'ip_address' => '192.168.1.51',
            'port' => 80,
            'is_online' => false,
        ]);

        app(HikvisionDeviceService::class)->refreshOnlineStatus($device);

        $this->assertTrue($device->fresh()->is_online);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains((string) $request['text'], 'Chiqish-1')
                && str_contains((string) $request['text'], 'online');
        });
    }
}
