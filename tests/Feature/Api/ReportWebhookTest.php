<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'department_name' => 'IT bo\'limi',
            'device_name' => 'Kirish 1',
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
            'device_name' => 'Chiqish 2',
            'type' => Report::TYPE_EXIT,
        ]);
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
