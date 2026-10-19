<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class BiocontrolAccessTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    public function test_guest_cannot_access_device_diagnostics(): void
    {
        $device = $this->createDevice();

        $this->get(route('site.biocontrol', $device))->assertRedirect(route('login'));
    }

    public function test_user_without_devices_permission_cannot_reach_the_device(): void
    {
        Http::fake();
        $device = $this->createDevice();

        $user = $this->createUserWithPermissions([
            config('menu_permissions.home'),
        ]);

        $this->actingAs($user)
            ->get(route('site.biocontrol', $device))
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_http_host_get_endpoint_no_longer_rewrites_device_settings(): void
    {
        // Qurilmalar boshqa tizimlar bilan umumiy: HTTP Listening faqat "Ulash" (POST + huquq) orqali yoziladi.
        Http::fake();
        $device = $this->createDevice();

        $this->actingAs($this->devicesUser())
            ->get('/biocontrol/devices/'.$device->id.'/http-host')
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_devices_user_gets_service_unavailable_without_device_credentials(): void
    {
        $device = $this->createDevice([
            'ip_address' => '',
            'user' => '',
            'password' => '',
        ]);

        $this->actingAs($this->devicesUser())
            ->get(route('site.biocontrol', $device))
            ->assertStatus(503);
    }

    public function test_devices_user_gets_bad_gateway_when_device_unreachable(): void
    {
        config(['hikvision.timeout' => 1]);

        $device = $this->createDevice([
            'ip_address' => '127.0.0.1',
            'port' => 1,
            'user' => 'device-admin',
            'password' => 'secret',
        ]);

        $this->actingAs($this->devicesUser())
            ->get(route('site.biocontrol', $device))
            ->assertStatus(502);
    }

    private function devicesUser(): User
    {
        return $this->createUserWithPermissions([
            config('menu_permissions.resources')['devices'],
        ]);
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
