<?php

namespace Tests\Feature\Hikvision;

use App\Models\Category;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\User;
use App\Services\Hikvision\EmployeePullService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmployeePullServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_pull_creates_employees_and_links_device_without_duplicates(): void
    {
        Http::fake([
            '*UserInfo/Search*' => Http::sequence()
                ->push([
                    'UserInfoSearch' => [
                        'searchID' => 'biocontrol-users-1-0',
                        'responseStatusStrg' => 'MORE',
                        'numOfMatches' => 1,
                        'UserInfo' => [
                            [
                                'employeeNo' => '1001',
                                'name' => 'Ali Valiyev',
                                'Valid' => [
                                    'beginTime' => '2026-01-01T00:00:00',
                                    'endTime' => '2030-12-31T23:59:59',
                                ],
                            ],
                        ],
                    ],
                ], 200)
                ->push([
                    'UserInfoSearch' => [
                        'searchID' => 'biocontrol-users-1-1',
                        'responseStatusStrg' => 'OK',
                        'numOfMatches' => 1,
                        'UserInfo' => [
                            [
                                'employeeNo' => '1002',
                                'name' => 'Dilnoza Karimova',
                                'Valid' => [
                                    'beginTime' => '2026-01-01T00:00:00',
                                    'endTime' => '2030-12-31T23:59:59',
                                ],
                            ],
                        ],
                    ],
                ], 200)
                // Second pull — same users again
                ->push([
                    'UserInfoSearch' => [
                        'searchID' => 'biocontrol-users-1-0',
                        'responseStatusStrg' => 'OK',
                        'numOfMatches' => 2,
                        'UserInfo' => [
                            [
                                'employeeNo' => '1001',
                                'name' => 'Ali Valiyev',
                                'Valid' => [
                                    'beginTime' => '2026-01-01T00:00:00',
                                    'endTime' => '2030-12-31T23:59:59',
                                ],
                            ],
                            [
                                'employeeNo' => '1002',
                                'name' => 'Dilnoza Karimova',
                                'Valid' => [
                                    'beginTime' => '2026-01-01T00:00:00',
                                    'endTime' => '2030-12-31T23:59:59',
                                ],
                            ],
                        ],
                    ],
                ], 200),
            '*FDSearch*' => Http::response('', 404),
            '*picture*' => Http::response('', 404),
        ]);

        $device = Device::factory()->create([
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'is_online' => true,
        ]);

        $service = app(EmployeePullService::class);

        $first = $service->pullDevice($device);
        $this->assertSame(2, $first['created']);
        $this->assertSame(0, $first['updated']);
        $this->assertSame(0, $first['failed']);

        $this->assertDatabaseCount('employees', 2);
        $this->assertDatabaseHas('employees', ['card' => '1001', 'name' => 'Ali Valiyev']);
        $this->assertDatabaseHas('employees', ['card' => '1002', 'name' => 'Dilnoza Karimova']);
        $this->assertDatabaseHas('departments', ['name' => 'Qurilmadan']);
        $this->assertDatabaseHas('schedules', ['name' => 'Standart']);

        $this->assertSame(2, $device->employees()->count());

        $second = $service->pullDevice($device);
        $this->assertSame(0, $second['created']);
        $this->assertSame(2, $second['skipped']);
        $this->assertDatabaseCount('employees', 2);
        $this->assertSame(2, $device->employees()->count());
    }

    public function test_pull_updates_existing_employee_name_and_attaches_second_device(): void
    {
        Http::fake([
            '*UserInfo/Search*' => Http::response([
                'UserInfoSearch' => [
                    'responseStatusStrg' => 'OK',
                    'UserInfo' => [
                        [
                            'employeeNo' => '5555',
                            'name' => 'Yangilangan Ism',
                            'Valid' => [
                                'beginTime' => '2026-02-01T00:00:00',
                                'endTime' => '2031-01-01T00:00:00',
                            ],
                        ],
                    ],
                ],
            ], 200),
            '*FDSearch*' => Http::response('', 404),
            '*picture*' => Http::response('', 404),
        ]);

        $department = Department::factory()->create(['name' => 'IT']);
        $employee = Employee::factory()->create([
            'card' => '5555',
            'name' => 'Eski Ism',
            'department_id' => $department->id,
        ]);

        $device = Device::factory()->create([
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'is_online' => true,
        ]);

        $result = app(EmployeePullService::class)->pullDevice($device);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['updated']);

        $employee->refresh();
        $this->assertSame('Yangilangan Ism', $employee->name);
        $this->assertSame($department->id, $employee->department_id);
        $this->assertTrue($device->employees()->where('employees.id', $employee->id)->exists());
        $this->assertDatabaseCount('employees', 1);
    }

    public function test_pull_downloads_face_from_pic_url_and_saves_to_faces_table(): void
    {
        $jpeg = "\xFF\xD8\xFF\xE0".str_repeat("\x00", 64)."\xFF\xD9";

        Http::fake([
            '*UserInfo/Search*' => Http::response([
                'UserInfoSearch' => [
                    'responseStatusStrg' => 'OK',
                    'UserInfo' => [
                        [
                            'employeeNo' => '8001',
                            'name' => 'Yuzli Xodim',
                            'picURL' => '/LOCALS/pic?id=8001',
                            'Valid' => [
                                'beginTime' => '2026-01-01T00:00:00',
                                'endTime' => '2030-12-31T23:59:59',
                            ],
                        ],
                    ],
                ],
            ], 200),
            '*LOCALS/pic*' => Http::response($jpeg, 200, ['Content-Type' => 'image/jpeg']),
            '*FDSearch*' => Http::response('', 404),
            '*picture*' => Http::response('', 404),
        ]);

        $device = Device::factory()->create([
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'is_online' => true,
        ]);

        $result = app(EmployeePullService::class)->pullDevice($device);

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['faces']);

        $employee = Employee::query()->where('card', '8001')->first();
        $this->assertNotNull($employee);
        $this->assertNotNull($employee->face);
        $this->assertSame(base64_encode($jpeg), $employee->face->face);
    }

    public function test_pull_downloads_face_via_fdsearch_face_url_fallback(): void
    {
        $jpeg = "\xFF\xD8\xFF\xE0".str_repeat("\x01", 64)."\xFF\xD9";

        Http::fake([
            '*UserInfo/Search*' => Http::response([
                'UserInfoSearch' => [
                    'responseStatusStrg' => 'OK',
                    'UserInfo' => [
                        [
                            'employeeNo' => '8002',
                            'name' => 'FDSearch Xodim',
                            'Valid' => [
                                'beginTime' => '2026-01-01T00:00:00',
                                'endTime' => '2030-12-31T23:59:59',
                            ],
                        ],
                    ],
                ],
            ], 200),
            '*FDSearch*' => Http::response([
                'responseStatusStrg' => 'OK',
                'MatchList' => [
                    [
                        'FPID' => '8002',
                        'faceURL' => '/ISAPI/Intelligent/FDLib/1/picture/8002',
                    ],
                ],
            ], 200),
            '*picture/8002*' => Http::response($jpeg, 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $device = Device::factory()->create([
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'is_online' => true,
        ]);

        $result = app(EmployeePullService::class)->pullDevice($device);

        $this->assertSame(1, $result['faces']);
        $this->assertDatabaseHas('faces', [
            'employee_id' => Employee::query()->where('card', '8002')->value('id'),
        ]);
    }
}
