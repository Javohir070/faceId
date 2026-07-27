<?php

namespace Tests\Feature\Hikvision;

use App\Models\Category;
use App\Models\Device;
use App\Models\Employee;
use App\Models\User;
use App\Services\Hikvision\HikvisionUserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HikvisionUserServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_user_uses_record_endpoint_and_uploads_face(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push('', 200)
                ->push('', 200),
        ]);

        $owner = User::factory()->create();
        $category = Category::factory()->create(['name' => 'Kirish']);
        $device = Device::factory()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
        ]);

        $employee = Employee::factory()->create();
        $employee->face()->create([
            'face' => base64_encode('fake-image-bytes-for-test'),
        ]);

        $service = app(HikvisionUserService::class);
        $result = $service->addOrUpdateUser($device, $employee->fresh(['face']));

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/ISAPI/AccessControl/UserInfo/Record');
        });
    }

    public function test_device_exposes_isapi_base_url_and_credentials_check(): void
    {
        $owner = User::factory()->create();
        $category = Category::factory()->create(['name' => 'Kirish']);
        $device = Device::factory()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'ip_address' => '192.168.100.232',
            'port' => 8000,
            'user' => 'admin',
            'password' => 'secret',
        ]);

        $this->assertSame('http://192.168.100.232:8000', $device->isapiBaseUrl());
        $this->assertTrue($device->hasIsapiCredentials());
        $this->assertContains('192.168.100.232', Device::trustedIpAddresses());
    }
}
