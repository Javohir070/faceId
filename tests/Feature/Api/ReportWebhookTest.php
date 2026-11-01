<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'hikvision.webhook_secret' => 'test-webhook-secret',
        ]);
    }

    public function test_webhook_creates_report_for_employee_entry(): void
    {
        $department = Department::factory()->create(['name' => 'IT bo\'limi']);
        $employee = Employee::factory()->create([
            'name' => 'Ali Valiyev',
            'card' => '1001',
            'department_id' => $department->id,
        ]);

        $device = $this->registerDevice('10.0.0.8', 'Kirish 1', Device::TYPE_ENTRY);

        $this->postJson('/api/webhooks/hikvision/events', [
            'event_log' => json_encode([
                'ipAddress' => '10.0.0.8',
                'dateTime' => '2026-07-21T16:40:22+05:00',
                'AccessControllerEvent' => [
                    'employeeNoString' => '1001',
                    'cardNo' => '998877',
                    'name' => 'Ali Valiyev',
                    'majorEventType' => 5,
                    'subEventType' => 75,
                    'currentVerifyMode' => 'face',
                    'attendanceStatus' => 'checkIn',
                ],
            ]),
        ], [
            'X-Webhook-Secret' => 'test-webhook-secret',
        ])->assertOk();

        $this->assertDatabaseHas('reports', [
            'employee_id' => $employee->id,
            'department_id' => $department->id,
            'device_id' => $device->id,
            'employee_name' => 'Ali Valiyev',
            'type' => Report::TYPE_ENTRY,
        ]);

        $report = Report::query()->first();
        $this->assertSame('Kirish', $report->type_label);
        $this->assertSame('2026-07-21 16:40:22', $report->occurred_at->format('Y-m-d H:i:s'));
    }

    public function test_webhook_creates_exit_report_from_device_type(): void
    {
        Employee::factory()->create([
            'name' => 'Artur',
            'card' => '2002',
        ]);

        $this->registerDevice('10.0.0.9', 'Chiqish 2', Device::TYPE_EXIT);

        $this->postJson('/api/webhooks/hikvision/events', [
            'event_log' => json_encode([
                'ipAddress' => '10.0.0.9',
                'dateTime' => '2026-07-21T16:36:57+05:00',
                'AccessControllerEvent' => [
                    'employeeNoString' => '2002',
                    'name' => 'Artur',
                    'majorEventType' => 5,
                    'subEventType' => 75,
                    'currentVerifyMode' => 'face',
                ],
            ]),
        ], [
            'X-Webhook-Secret' => 'test-webhook-secret',
        ])->assertOk();

        $this->assertDatabaseHas('reports', [
            'employee_name' => 'Artur',
            'device_id' => Device::query()->where('ip_address', '10.0.0.9')->value('id'),
            'type' => Report::TYPE_EXIT,
        ]);
    }

    /**
     * Terminal ekranidagi tugmalar ("Ishga keldim" / "Chiqdim", tanaffus, qo‘shimcha ish) hodisaga
     * attendanceStatus bo‘lib yoziladi. Qurilma turi faqat tugma bosilmaganda hisobga olinadi.
     *
     * @return array<string, array{0: ?string, 1: int, 2: int}>
     */
    public static function attendanceStatuses(): array
    {
        return [
            'ishga keldim' => ['checkIn', Device::TYPE_EXIT, Report::TYPE_ENTRY],
            'chiqdim' => ['checkOut', Device::TYPE_ENTRY, Report::TYPE_EXIT],
            'tanaffusga chiqdi' => ['breakOut', Device::TYPE_ENTRY, Report::TYPE_EXIT],
            'tanaffusdan qaytdi' => ['breakIn', Device::TYPE_EXIT, Report::TYPE_ENTRY],
            'qo‘shimcha ishga keldi' => ['overtimeIn', Device::TYPE_EXIT, Report::TYPE_ENTRY],
            'qo‘shimcha ishdan ketdi' => ['overtimeOut', Device::TYPE_ENTRY, Report::TYPE_EXIT],
            'tugma bosilmagan' => ['undefined', Device::TYPE_EXIT, Report::TYPE_EXIT],
            'holat yo‘q' => [null, Device::TYPE_ENTRY, Report::TYPE_ENTRY],
        ];
    }

    #[DataProvider('attendanceStatuses')]
    public function test_terminal_attendance_button_decides_entry_or_exit(?string $status, int $deviceType, int $expected): void
    {
        Employee::factory()->create(['card' => '3003']);
        $device = $this->registerDevice('10.0.0.20', 'Terminal', $deviceType);

        $this->postJson('/api/webhooks/hikvision/events', [
            'event_log' => json_encode([
                'ipAddress' => '10.0.0.20',
                'dateTime' => '2026-10-31T09:00:00+05:00',
                'AccessControllerEvent' => array_filter([
                    'employeeNoString' => '3003',
                    'majorEventType' => 5,
                    'subEventType' => 75,
                    'currentVerifyMode' => 'face',
                    'attendanceStatus' => $status,
                ], fn ($value) => $value !== null),
            ]),
        ], [
            'X-Webhook-Secret' => 'test-webhook-secret',
        ])->assertOk();

        $this->assertDatabaseHas('reports', ['device_id' => $device->id, 'type' => $expected]);
    }

    public function test_webhook_time_from_terminal_in_other_timezone_is_converted_to_tashkent(): void
    {
        Employee::factory()->create(['card' => '4004']);
        $device = $this->registerDevice('10.0.0.31', 'Terminal UTC+8', Device::TYPE_ENTRY);

        $this->postJson('/api/webhooks/hikvision/events', [
            'event_log' => json_encode([
                'ipAddress' => '10.0.0.31',
                'dateTime' => '2026-11-01T20:52:05+08:00',
                'AccessControllerEvent' => [
                    'employeeNoString' => '4004',
                    'majorEventType' => 5,
                    'subEventType' => 75,
                ],
            ]),
        ], [
            'X-Webhook-Secret' => 'test-webhook-secret',
        ])->assertOk();

        $this->assertSame('2026-11-01 17:52:05', Report::query()->where('device_id', $device->id)->value('occurred_at')->format('Y-m-d H:i:s'));
    }

    private function registerDevice(string $ipAddress, string $name, int $type): Device
    {
        $owner = User::factory()->create();

        return Device::factory()->create([
            'user_id' => $owner->id,
            'category_id' => Category::factory()->create()->id,
            'name' => $name,
            'ip_address' => $ipAddress,
            'port' => 80,
            'type' => $type,
            'user' => 'admin',
            'password' => 'secret',
        ]);
    }
}
