<?php

namespace Tests\Unit\Hikvision;

use App\Models\Category;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceStreamableTest extends TestCase
{
    use RefreshDatabase;

    public function test_streamable_from_database_returns_devices_with_isapi_credentials(): void
    {
        $owner = User::factory()->create();
        $category = Category::factory()->create();

        $ready = Device::factory()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'ip_address' => '192.168.1.64',
            'port' => 80,
            'user' => 'admin',
            'password' => 'secret123',
        ]);

        Device::factory()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'ip_address' => '',
            'port' => 80,
            'user' => 'admin',
            'password' => 'secret123',
        ]);

        $devices = Device::streamableFromDatabase();

        $this->assertCount(1, $devices);
        $this->assertTrue($devices->first()->is($ready));
    }
}
