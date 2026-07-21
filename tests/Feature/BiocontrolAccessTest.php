<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BiocontrolAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_biocontrol_endpoints(): void
    {
        $this->get(route('site.biocontrol'))->assertRedirect(route('login'));
        $this->get(route('site.http-host'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_gets_service_unavailable_without_device_config(): void
    {
        config([
            'hikvision.base_uri' => '',
            'hikvision.username' => '',
            'hikvision.password' => '',
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('site.biocontrol'))
            ->assertStatus(503);
    }

    public function test_authenticated_user_gets_bad_gateway_when_device_unreachable(): void
    {
        config([
            'hikvision.base_uri' => 'http://127.0.0.1:1',
            'hikvision.username' => 'device-admin',
            'hikvision.password' => 'secret',
            'hikvision.timeout' => 1,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('site.biocontrol'))
            ->assertStatus(502);
    }
}
