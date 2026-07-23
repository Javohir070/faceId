<?php

namespace Tests\Unit;

use App\Models\AccessControllerEvent;
use App\Models\Category;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Report;
use App\Models\Schedule;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportServiceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_from_access_event_is_idempotent_per_event(): void
    {
        $department = Department::factory()->create();
        $schedule = Schedule::factory()->create();
        $employee = Employee::factory()->create([
            'card' => '7777',
            'department_id' => $department->id,
            'schedule_id' => $schedule->id,
        ]);

        $owner = User::factory()->create();
        $category = Category::factory()->create();
        $device = Device::factory()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'ip_address' => '10.0.0.55',
            'type' => 0,
        ]);

        $event = AccessControllerEvent::factory()->create([
            'employee_no' => '7777',
            'device_ip' => '10.0.0.55',
            'full_name' => $employee->name,
            'attendance_status' => 'checkIn',
            'event_time' => now(),
        ]);

        $service = app(ReportService::class);

        $first = $service->createFromAccessEvent($event);
        $second = $service->createFromAccessEvent($event);

        $this->assertNotNull($first);
        $this->assertTrue($first->is($second));
        $this->assertSame(1, Report::query()->where('access_controller_event_id', $event->id)->count());
        $this->assertTrue($event->employee->is($employee));
        $this->assertTrue($event->device->is($device));
    }
}
