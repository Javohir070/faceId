<?php

namespace Tests\Feature\Hikvision;

use App\Models\Category;
use App\Models\Device;
use App\Models\Employee;
use App\Models\User;
use App\Services\Hikvision\EmployeeSyncService;
use App\Services\Hikvision\HikvisionUserService;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
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

    public function test_rejected_face_keeps_the_old_face_and_shows_the_terminal_reason(): void
    {
        Http::fake([
            '*FDSetUp*' => Http::response([
                'statusCode' => 6,
                'statusString' => 'Invalid Content',
                'subStatusCode' => 'badParameters',
            ], 400),
            '*' => Http::response('', 200),
        ]);

        [$device, $employee] = $this->onlineDeviceAndEmployeeWithFace();

        $result = app(EmployeeSyncService::class)->transferOne($device, $employee);

        $expected = 'Terminal yuz rasmini qabul qilmadi: badParameters (HTTP 400)';
        $this->assertSame('failed', $result['status']);
        $this->assertSame($expected, $result['message']);
        $this->assertSame($expected, $employee->devices()->first()->pivot->sync_error);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'FDDelete'));
    }

    public function test_face_is_replaced_when_the_terminal_says_it_already_exists(): void
    {
        Http::fake([
            '*FDSetUp*' => Http::sequence()
                ->push(['statusCode' => 6, 'subStatusCode' => 'deviceUserAlreadyExistFace'], 400)
                ->push('', 200),
            '*' => Http::response('', 200),
        ]);

        [$device, $employee] = $this->onlineDeviceAndEmployeeWithFace();

        $result = app(EmployeeSyncService::class)->transferOne($device, $employee);

        $this->assertSame('success', $result['status']);
        $this->assertNull($employee->devices()->first()->pivot->sync_error);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'FDDelete'));
    }

    public function test_real_401_is_reported_as_rejected_login(): void
    {
        Http::fake([
            '*FDSetUp*' => Http::response('', 401),
            '*' => Http::response('', 200),
        ]);

        [$device, $employee] = $this->onlineDeviceAndEmployeeWithFace();

        $result = app(EmployeeSyncService::class)->transferOne($device, $employee);

        $this->assertSame('Terminal yuz rasmini qabul qilmadi: login yoki parol rad etildi (HTTP 401)', $result['message']);
    }

    public function test_connection_break_is_not_reported_as_a_wrong_password(): void
    {
        Http::fake([
            '*FDSetUp*' => fn ($request) => Create::rejectionFor(new GuzzleRequestException(
                'cURL error 56: Connection was reset',
                new Psr7Request('PUT', $request->url()),
                new Psr7Response(401),
            )),
            '*' => Http::response('', 200),
        ]);

        [$device, $employee] = $this->onlineDeviceAndEmployeeWithFace();

        $result = app(EmployeeSyncService::class)->transferOne($device, $employee);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('Terminal bilan aloqa uzildi', $result['message']);
        $this->assertSame('Terminal bilan aloqa uzildi', $employee->devices()->first()->pivot->sync_error);
    }

    public function test_rejected_user_shows_the_terminal_reason(): void
    {
        Http::fake([
            '*UserInfo/*' => Http::response(['statusCode' => 6, 'subStatusCode' => 'badParameters'], 400),
            '*' => Http::response('', 200),
        ]);

        [$device, $employee] = $this->onlineDeviceAndEmployeeWithFace();

        $result = app(EmployeeSyncService::class)->transferOne($device, $employee);

        $this->assertSame('Terminal xodimni qabul qilmadi: badParameters (HTTP 400)', $result['message']);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'FDSetUp'));
    }

    public function test_fetch_all_users_paginates_user_info_search(): void
    {
        Http::fake([
            '*UserInfo/Search*' => Http::sequence()
                ->push([
                    'UserInfoSearch' => [
                        'responseStatusStrg' => 'MORE',
                        'UserInfo' => [
                            ['employeeNo' => '1', 'name' => 'One'],
                        ],
                    ],
                ], 200)
                ->push([
                    'UserInfoSearch' => [
                        'responseStatusStrg' => 'OK',
                        'UserInfo' => [
                            ['employeeNo' => '2', 'name' => 'Two'],
                        ],
                    ],
                ], 200),
        ]);

        $device = Device::factory()->create([
            'user_id' => User::factory(),
            'category_id' => Category::factory()->create(['name' => 'Kirish']),
        ]);

        $users = app(HikvisionUserService::class)->fetchAllUsers($device);

        $this->assertCount(2, $users);
        $this->assertSame('1', $users[0]['employeeNo']);
        $this->assertSame('2', $users[1]['employeeNo']);
    }

    public function test_download_face_base64_from_pic_url(): void
    {
        $jpeg = "\xFF\xD8\xFF\xE0".str_repeat("\x02", 64)."\xFF\xD9";

        Http::fake([
            '*LOCALS/face*' => Http::response($jpeg, 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $device = Device::factory()->create([
            'user_id' => User::factory(),
            'category_id' => Category::factory()->create(['name' => 'Kirish']),
        ]);

        $base64 = app(HikvisionUserService::class)->downloadFaceBase64(
            $device,
            '42',
            '/LOCALS/face?id=42',
        );

        $this->assertSame(base64_encode($jpeg), $base64);
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

    /**
     * @return array{Device, Employee}
     */
    private function onlineDeviceAndEmployeeWithFace(): array
    {
        $device = Device::factory()->create([
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'is_online' => true,
        ]);
        $employee = Employee::factory()->create();
        $employee->face()->create(['face' => base64_encode('fake-image-bytes-for-test')]);

        return [$device, $employee->fresh(['face'])];
    }
}
