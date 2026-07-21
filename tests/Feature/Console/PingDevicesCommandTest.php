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
}
