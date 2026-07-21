<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BiocontrolAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_biocontrol_endpoints(): void
    {
        $device = $this->createDevice();

        $this->get(route('site.biocontrol', $device))->assertRedirect(route('login'));
        $this->get(route('site.http-host', $device))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_gets_service_unavailable_without_device_credentials(): void
    {
        $owner = User::factory()->create();
        $category = Category::factory()->create();
        $device = Device::factory()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'ip_address' => '',
            'user' => '',
            'password' => '',
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('site.biocontrol', $device))
            ->assertStatus(503);
    }

    public function test_authenticated_user_gets_bad_gateway_when_device_unreachable(): void
    {
        config(['hikvision.timeout' => 1]);

        $device = $this->createDevice([
            'ip_address' => '127.0.0.1',
            'port' => 1,
            'user' => 'device-admin',
            'password' => 'secret',
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('site.biocontrol', $device))
            ->assertStatus(502);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createDevice(array $attributes = []): Device
    {
        $owner = User::factory()->create();
        $category = Category::factory()->create();

        return Device::factory()->create(array_merge([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'ip_address' => '192.168.100.115',
            'port' => 8000,
            'user' => 'admin',
            'password' => 'secret',
        ], $attributes));
    }
}
