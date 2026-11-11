<?php

namespace Tests\Feature\Api;

use App\Enums\NoteType;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Face;
use App\Models\Note;
use App\Models\Report;
use App\Models\Schedule;
use App\Services\Hikvision\EmployeeSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use Tests\TestCase;

class LegacyApiTest extends TestCase
{
    use RefreshDatabase;

    private const CONSUMER_IP = '10.1.1.1';

    /** 1×1 PNG */
    private const FACE = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private Department $department;

    private Schedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->department = Department::factory()->create();
        $this->schedule = Schedule::factory()->create(['enter_time' => '09:00', 'exit_time' => '18:00']);

        config([
            'legacy_api.allowed_ips' => [self::CONSUMER_IP],
            'legacy_api.department_id' => $this->department->id,
            'legacy_api.schedule_id' => $this->schedule->id,
            'hikvision.event_timezone' => 'Asia/Tashkent',
        ]);
    }

    public function test_request_from_unlisted_ip_is_rejected(): void
    {
        $this->legacy(['action' => 'attendance'], '10.9.9.9')
            ->assertForbidden()
            ->assertExactJson(['status' => false, 'alerts' => ['Access denied']]);
    }

    public function test_forwarded_header_cannot_bypass_the_ip_filter(): void
    {
        $this->legacy(['action' => 'attendance'], '203.0.113.9', ['HTTP_X_FORWARDED_FOR' => self::CONSUMER_IP])
            ->assertForbidden();
    }

    public function test_forwarded_header_from_local_proxy_is_trusted(): void
    {
        $this->legacy(['action' => 'attendance'], '127.0.0.1', ['HTTP_X_FORWARDED_FOR' => self::CONSUMER_IP])
            ->assertOk()
            ->assertJsonPath('status', true);
    }

    public function test_api_is_closed_when_no_ip_is_configured(): void
    {
        config(['legacy_api.allowed_ips' => []]);

        $this->legacy(['action' => 'attendance'])->assertForbidden();
    }

    public function test_empty_body_and_unknown_action_return_false_status_like_old_api(): void
    {
        $this->call('POST', '/api/legacy', server: ['REMOTE_ADDR' => self::CONSUMER_IP], content: '')
            ->assertOk()
            ->assertExactJson(['status' => false])
            ->assertHeader('Access-Control-Allow-Origin', '*');

        $this->legacy(['action' => 'unknown'])->assertOk()->assertExactJson(['status' => false]);
    }

    public function test_add_employee_returns_old_validation_alerts(): void
    {
        $this->partialMock(EmployeeSyncService::class, fn (MockInterface $mock) => $mock->shouldNotReceive('transferOne'));

        $this->legacy(['action' => 'add_employee'])
            ->assertOk()
            ->assertExactJson([
                'status' => false,
                'alerts' => ['Enter name', 'Select template', 'Enter start datetime', 'Enter end datetime'],
            ]);

        $this->assertDatabaseCount('employees', 0);
    }

    public function test_add_employee_creates_employee_with_face_and_writes_it_to_every_device(): void
    {
        $first = Device::factory()->create(['ip_address' => '10.0.0.8']);
        $second = Device::factory()->create(['ip_address' => '10.0.0.9']);
        $pushed = [];

        $this->partialMock(EmployeeSyncService::class, function (MockInterface $mock) use (&$pushed): void {
            $mock->shouldReceive('transferOne')->twice()->andReturnUsing(function (Device $device, Employee $employee) use (&$pushed): array {
                $pushed[] = [$device->id, $employee->face?->face];

                return ['status' => 'success', 'message' => 'OK'];
            });
        });

        $this->legacy($this->employeePayload(['face' => 'data:image/png;base64,'.self::FACE]))
            ->assertOk()
            ->assertExactJson(['status' => true, 'alerts' => ['Saved successfully']]);

        $employee = Employee::query()->where('card', '12345678901234')->firstOrFail();

        $this->assertSame('Ali Valiyev', $employee->name);
        $this->assertSame($this->department->id, $employee->department_id);
        $this->assertSame($this->schedule->id, $employee->schedule_id);
        $this->assertSame('2026-01-01 00:00:00', $employee->valid_from_dt->format('Y-m-d H:i:s'));
        $this->assertSame('2027-12-31 23:59:59', $employee->valid_to_dt->format('Y-m-d H:i:s'));
        $this->assertSame(self::FACE, Face::query()->where('employee_id', $employee->id)->value('face'));
        $this->assertSame([[$first->id, self::FACE], [$second->id, self::FACE]], $pushed);
    }

    public function test_add_employee_reports_device_failures_in_old_format_but_keeps_the_employee(): void
    {
        $device = Device::factory()->create(['ip_address' => '10.0.0.8']);

        $this->partialMock(EmployeeSyncService::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('transferOne')
            ->once()
            ->andReturn(['status' => 'failed', 'message' => 'Terminal bilan aloqa uzildi']));

        $response = $this->legacy($this->employeePayload(['face' => self::FACE]))->assertOk();

        $employee = Employee::query()->where('card', '12345678901234')->firstOrFail();

        $response->assertExactJson([
            'status' => false,
            'alerts' => [[
                'status' => false,
                'message' => "IP: {$device->ip_address}; ID: {$employee->id}; Error: Terminal bilan aloqa uzildi",
            ]],
        ]);
    }

    public function test_add_employee_updates_existing_card_and_keeps_its_department(): void
    {
        config(['legacy_api.department_id' => null, 'legacy_api.schedule_id' => null]);
        $otherDepartment = Department::factory()->create();
        $employee = Employee::factory()->create(['card' => '12345678901234', 'department_id' => $otherDepartment->id]);

        $this->partialMock(EmployeeSyncService::class, fn (MockInterface $mock) => $mock->shouldNotReceive('transferOne'));

        $this->legacy($this->employeePayload(['name' => 'Yangi Ism', 'end' => '2028-06-30 18:00:00']))
            ->assertExactJson(['status' => true, 'alerts' => ['Saved successfully']]);

        $employee->refresh();
        $this->assertSame('Yangi Ism', $employee->name);
        $this->assertSame($otherDepartment->id, $employee->department_id);
        $this->assertSame('2028-06-30 18:00:00', $employee->valid_to_dt->format('Y-m-d H:i:s'));
        $this->assertDatabaseCount('employees', 1);
    }

    public function test_new_card_needs_configured_department_and_schedule(): void
    {
        config(['legacy_api.department_id' => null]);

        $this->legacy($this->employeePayload())
            ->assertExactJson(['status' => false, 'alerts' => ['LEGACY_API_DEPARTMENT_ID is not configured']]);

        $this->assertDatabaseCount('employees', 0);
    }

    public function test_blocked_template_ends_validity_now_without_expiry_message(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 10:00:00'));

        $this->legacy($this->employeePayload(['template' => 2]))
            ->assertExactJson(['status' => true, 'alerts' => ['Saved successfully']]);

        $employee = Employee::query()->where('card', '12345678901234')->firstOrFail();
        $this->assertSame('2026-11-11 10:00:00', $employee->valid_to_dt->format('Y-m-d H:i:s'));
        $this->assertSame(Employee::VALIDITY_EXPIRED, $employee->validityStatus(now()->addSecond()));
        $this->assertNotNull($employee->expiry_notified_at);
    }

    public function test_delete_employee_removes_it_from_devices_then_from_database(): void
    {
        $device = Device::factory()->create(['ip_address' => '10.0.0.8']);
        $employee = Employee::factory()->create(['card' => '11111111111111']);
        $employee->devices()->attach($device->id, ['synced_at' => now()]);

        $this->partialMock(EmployeeSyncService::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('removeFromDevices')
            ->once()
            ->withArgs(fn (Employee $target, array $deviceIds): bool => $target->is($employee) && $deviceIds === [$device->id])
            ->andReturnUsing(function (Employee $target) use ($device): array {
                $target->devices()->detach($device->id);

                return ['success' => 1, 'failed' => 0, 'errors' => []];
            }));

        $this->legacy(['action' => 'delete_employee', 'cards' => ['11111111111111', 'not-existing']])
            ->assertExactJson(['status' => true, 'alerts' => ['Deleted successfully']]);

        $this->assertModelMissing($employee);
    }

    public function test_delete_employee_keeps_employee_when_a_device_fails(): void
    {
        $device = Device::factory()->create(['ip_address' => '10.0.0.8']);
        $employee = Employee::factory()->create(['card' => '11111111111111']);
        $employee->devices()->attach($device->id, ['synced_at' => now()]);

        $this->partialMock(EmployeeSyncService::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('removeFromDevices')
            ->andReturn(['success' => 0, 'failed' => 1, 'errors' => [['ip' => '10.0.0.8', 'message' => 'Terminal bilan aloqa uzildi']]]));

        $this->legacy(['action' => 'delete_employee', 'cards' => ['11111111111111']])
            ->assertExactJson(['status' => false, 'alerts' => ['10.0.0.8 Terminal bilan aloqa uzildi']]);

        $this->assertModelExists($employee);
    }

    public function test_attendance_returns_todays_first_entry_with_old_flags_and_notes(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 12:00:00', 'Asia/Tashkent'));
        $device = Device::factory()->create();

        $early = $this->employee('Erta', '1001');
        $this->report($early, $device, Report::TYPE_ENTRY, '2026-11-11 09:40:00');
        $this->report($early, $device, Report::TYPE_ENTRY, '2026-11-11 09:05:00');
        $this->report($early, $device, Report::TYPE_ENTRY, '2026-11-10 08:00:00');

        $late = $this->employee('Kechikkan', '1002');
        $this->report($late, $device, Report::TYPE_ENTRY, '2026-11-11 09:16:00');
        Note::factory()->create(['employee_id' => $late->id, 'type' => NoteType::CameLate, 'dt' => '2026-11-11', 'note' => 'Shifoxonada']);

        $veryLate = $this->employee('Juda kech', '1003');
        $this->report($veryLate, $device, Report::TYPE_ENTRY, '2026-11-11 09:30:01');
        $this->report($veryLate, $device, Report::TYPE_EXIT, '2026-11-11 08:00:00');

        $absent = $this->employee('Kelmagan', '1004');
        Note::factory()->create(['employee_id' => $absent->id, 'type' => NoteType::Absent, 'dt' => '2026-11-11', 'note' => 'Kasal']);
        Note::factory()->create(['employee_id' => $absent->id, 'type' => NoteType::Absent, 'dt' => '2026-11-09', 'note' => 'Eski']);

        $this->legacy(['action' => 'attendance'])
            ->assertOk()
            ->assertExactJson([
                'status' => true,
                'attendance' => [
                    $this->attendanceRow('Erta', '1001', '2026-11-11 09:05:00', before: true),
                    $this->attendanceRow('Kechikkan', '1002', '2026-11-11 09:16:00', between: true, noteType: 'came_late', note: 'Shifoxonada'),
                    $this->attendanceRow('Juda kech', '1003', '2026-11-11 09:30:01', after: true),
                    $this->attendanceRow('Kelmagan', '1004', null, noteType: 'absent', note: 'Kasal'),
                ],
            ]);

        $this->legacy(['action' => 'attendance', 'cards' => ['1002']])
            ->assertJsonCount(1, 'attendance')
            ->assertJsonPath('attendance.0.card', '1002');
    }

    public function test_add_note_creates_or_updates_a_note_by_card(): void
    {
        $employee = $this->employee('Ali', '1001');
        $payload = ['action' => 'add_note', 'card' => '1001', 'type' => 'left_early', 'dt' => '2026-11-11', 'note' => 'Erta ketdi'];

        $this->legacy($payload)->assertExactJson(['status' => true]);
        $this->legacy([...$payload, 'note' => 'Tuzatilgan'])->assertExactJson(['status' => true]);

        $note = Note::query()->sole();
        $this->assertSame($employee->id, $note->employee_id);
        $this->assertSame(NoteType::LeftEarly, $note->type);
        $this->assertSame('2026-11-11', $note->dt->toDateString());
        $this->assertSame('Tuzatilgan', $note->note);

        $this->legacy(['action' => 'add_note', 'card' => '9999', 'type' => 'late', 'dt' => '2026-11-11', 'note' => ''])
            ->assertExactJson(['status' => false, 'alerts' => ['Invalid card number', 'Invalid note type', 'Invalid note text']]);
    }

    public function test_get_reports_filters_and_returns_values_as_strings_like_old_api(): void
    {
        $first = Device::factory()->create(['ip_address' => '10.0.0.8']);
        $second = Device::factory()->create(['ip_address' => '10.0.0.9']);
        $ali = $this->employee('Ali', '1001');
        $vali = $this->employee('Vali', '1002');

        $entry = $this->report($ali, $first, Report::TYPE_ENTRY, '2026-11-10 09:00:00');
        $exit = $this->report($ali, $second, Report::TYPE_EXIT, '2026-11-10 18:00:00');
        $this->report($vali, $first, Report::TYPE_ENTRY, '2026-11-10 09:30:00');
        $this->report($ali, $first, Report::TYPE_ENTRY, '2026-11-12 09:00:00');

        $this->legacy(['action' => 'get_reports', 'card' => '1001', 'start' => '2026-11-10 00:00:00', 'end' => '2026-11-10 23:59:59'])
            ->assertExactJson([
                'status' => true,
                'reports' => [
                    ['id' => (string) $entry->id, 'card' => '1001', 'ip' => '10.0.0.8', 'type' => '1', 'dt' => '2026-11-10 09:00:00'],
                    ['id' => (string) $exit->id, 'card' => '1001', 'ip' => '10.0.0.9', 'type' => '2', 'dt' => '2026-11-10 18:00:00'],
                ],
            ]);

        $this->legacy(['action' => 'get_reports', 'ip' => '10.0.0.8', 'type' => 1, 'start' => '2026-11-10', 'end' => '2026-11-11'])
            ->assertJsonCount(2, 'reports')
            ->assertJsonPath('reports.1.card', '1002');

        $this->legacy(['action' => 'get_reports'])
            ->assertExactJson(['status' => false, 'alerts' => ['Enter start datetime', 'Enter end datetime']]);
    }

    public function test_get_work_time_sums_daily_time_and_handles_night_shift(): void
    {
        $device = Device::factory()->create();
        $day = $this->employee('Kunduzgi', '1001');
        $this->report($day, $device, Report::TYPE_ENTRY, '2026-11-09 09:00:00');
        $this->report($day, $device, Report::TYPE_ENTRY, '2026-11-09 09:10:00');
        $this->report($day, $device, Report::TYPE_EXIT, '2026-11-09 13:00:00');
        $this->report($day, $device, Report::TYPE_EXIT, '2026-11-09 18:00:00');
        $this->report($day, $device, Report::TYPE_ENTRY, '2026-11-10 09:00:00');
        $this->report($day, $device, Report::TYPE_EXIT, '2026-11-10 13:30:00');
        $this->report($day, $device, Report::TYPE_ENTRY, '2026-11-11 09:00:00');

        $night = $this->employee('Tungi', '1002', Schedule::factory()->create(['enter_time' => '20:00', 'exit_time' => '08:00']));
        $this->report($night, $device, Report::TYPE_ENTRY, '2026-11-08 20:00:00');
        $this->report($night, $device, Report::TYPE_EXIT, '2026-11-09 08:00:00');
        $this->report($night, $device, Report::TYPE_ENTRY, '2026-11-10 20:00:00');
        $this->report($night, $device, Report::TYPE_EXIT, '2026-11-11 08:00:00');

        $this->legacy(['action' => 'get_work_time', 'start' => '2026-11-09 00:00:00', 'end' => '2026-11-11 23:59:59'])
            ->assertExactJson([
                'status' => true,
                'work_time' => [
                    [
                        'card' => '1001',
                        'days' => ['2026-11-09' => '09:00:00', '2026-11-10' => '04:30:00', '2026-11-11' => 0],
                        'total' => '13:30:00',
                    ],
                    [
                        'card' => '1002',
                        'days' => ['2026-11-09' => 0, '2026-11-10' => '12:00:00', '2026-11-11' => 0],
                        'total' => '12:00:00',
                    ],
                ],
            ]);

        $this->legacy(['action' => 'get_work_time', 'card' => '1002', 'start' => '2026-11-10', 'end' => '2026-11-10'])
            ->assertExactJson([
                'status' => true,
                'work_time' => [['card' => '1002', 'days' => ['2026-11-10' => '12:00:00'], 'total' => '12:00:00']],
            ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $server
     */
    private function legacy(array $payload, string $ip = self::CONSUMER_IP, array $server = []): TestResponse
    {
        return $this->call(
            'POST',
            '/api/legacy',
            server: ['REMOTE_ADDR' => $ip, 'CONTENT_TYPE' => 'application/json', ...$server],
            content: json_encode($payload),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function employeePayload(array $overrides = []): array
    {
        return [
            'action' => 'add_employee',
            'name' => 'Ali Valiyev',
            'card' => '12345678901234',
            'template' => 1,
            'finger' => 1,
            'fingerprint' => '',
            'face' => '',
            'start' => '2026-01-01 00:00:00',
            'end' => '2027-12-31 23:59:59',
            ...$overrides,
        ];
    }

    private function employee(string $name, string $card, ?Schedule $schedule = null): Employee
    {
        return Employee::factory()->create([
            'name' => $name,
            'card' => $card,
            'department_id' => $this->department->id,
            'schedule_id' => ($schedule ?? $this->schedule)->id,
        ]);
    }

    /**
     * Factory emas: ReportFactory definition’i qo‘shimcha xodim va qurilma yaratib yuboradi.
     */
    private function report(Employee $employee, Device $device, int $type, string $occurredAt): Report
    {
        return Report::query()->create([
            'employee_id' => $employee->id,
            'department_id' => $employee->department_id,
            'device_id' => $device->id,
            'employee_name' => $employee->name,
            'type' => $type,
            'occurred_at' => $occurredAt,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function attendanceRow(
        string $name,
        string $card,
        ?string $dt,
        bool $before = false,
        bool $between = false,
        bool $after = false,
        ?string $noteType = null,
        ?string $note = null,
    ): array {
        return [
            'name' => $name,
            'card' => $card,
            'note_type' => $noteType,
            'note' => $note,
            'dt' => $dt,
            'before_9_15' => $before,
            'between_9_15_9_30' => $between,
            'after_9_30' => $after,
            'absent' => $dt === null,
        ];
    }
}
