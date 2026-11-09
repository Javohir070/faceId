<?php

namespace Tests\Feature\Hikvision;

use App\Models\AccessControllerEvent;
use App\Models\Category;
use App\Models\Device;
use App\Models\Employee;
use App\Models\EventReconciliation;
use App\Models\Report;
use App\Models\User;
use App\Services\Hikvision\EventReconciliationService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTelegramAdmin;
use Tests\TestCase;

class EventReconciliationTest extends TestCase
{
    use CreatesTelegramAdmin;
    use RefreshDatabase;

    private const ALI = '11111111111111';

    private const VALI = '22222222222222';

    private const STRANGER = '99999999999999';

    private Device $device;

    private Employee $ali;

    /** @var list<string> */
    private array $requestedDays = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-10 01:00:00'));
        config(['telegram.token' => 'test-token']);
        $this->createTelegramSuperAdmin(['telegram_chat_id' => '111']);

        $this->device = Device::factory()->create([
            'name' => 'Nukus-1',
            'ip_address' => '192.168.100.115',
            'port' => 80,
            'type' => Device::TYPE_ENTRY,
            'is_online' => true,
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
        ]);
        $this->ali = Employee::factory()->create(['card' => self::ALI, 'name' => 'Ali']);
        Employee::factory()->create(['card' => self::VALI, 'name' => 'Vali']);
    }

    public function test_missing_events_of_our_employees_are_recovered_and_strangers_counted_apart(): void
    {
        $this->storeEvent(self::ALI, '2026-11-09 09:00:00');
        $this->fakeTerminal(['2026-11-09' => [
            [self::ALI, '09:00:00'],
            [self::VALI, '09:05:00'],
            [self::STRANGER, '09:10:00'],
            ['', '09:20:00'],
        ]]);

        $result = app(EventReconciliationService::class)->reconcile($this->device, Carbon::parse('2026-11-09'));

        $this->assertSame(EventReconciliation::STATUS_OK, $result->status);
        $this->assertSame(2, $result->device_count);
        $this->assertSame(2, $result->system_count);
        $this->assertSame(1, $result->recovered);
        $this->assertSame(1, $result->strangers);
        $this->assertDatabaseHas('reports', ['device_id' => $this->device->id, 'occurred_at' => '2026-11-09 09:05:00']);
    }

    public function test_extra_record_in_the_system_is_a_mismatch(): void
    {
        $this->storeEvent(self::ALI, '2026-11-09 09:00:00');
        $this->storeEvent(self::ALI, '2026-11-09 12:00:00');
        $this->fakeTerminal(['2026-11-09' => [[self::ALI, '09:00:00']]]);

        $result = app(EventReconciliationService::class)->reconcile($this->device, Carbon::parse('2026-11-09'));

        $this->assertSame(EventReconciliation::STATUS_MISMATCH, $result->status, (string) $result->error);
        $this->assertSame(1, $result->device_count);
        $this->assertSame(2, $result->system_count);
    }

    public function test_device_that_does_not_answer_is_recorded(): void
    {
        Http::fake(['*' => Http::response('xato', 500)]);

        $result = app(EventReconciliationService::class)->reconcile($this->device, Carbon::parse('2026-11-09'));

        $this->assertSame(EventReconciliation::STATUS_UNREACHABLE, $result->status);
        $this->assertNull($result->device_count);
    }

    public function test_offline_device_is_not_contacted(): void
    {
        Http::fake();
        $this->device->forceFill(['is_online' => false])->save();

        $result = app(EventReconciliationService::class)->reconcile($this->device, Carbon::parse('2026-11-09'));

        $this->assertSame(EventReconciliation::STATUS_UNREACHABLE, $result->status);
        Http::assertNothingSent();
    }

    public function test_nightly_run_rechecks_older_days_only_when_they_were_not_fine(): void
    {
        $this->record('2026-11-08', EventReconciliation::STATUS_OK);
        $this->record('2026-11-07', EventReconciliation::STATUS_MISMATCH);
        $this->fakeTerminal([]);

        $this->artisan('devices:reconcile-events')->assertSuccessful();

        sort($this->requestedDays);
        $this->assertSame(['2026-11-06', '2026-11-07', '2026-11-09'], $this->requestedDays);
    }

    public function test_problem_is_sent_to_telegram_once(): void
    {
        $this->storeEvent(self::ALI, '2026-11-09 09:00:00');
        $this->storeEvent(self::ALI, '2026-11-09 12:00:00');
        $this->fakeTerminal(['2026-11-09' => [[self::ALI, '09:00:00']]]);

        $this->artisan('devices:reconcile-events', ['--days' => 1])->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], 'Nukus-1')
            && str_contains((string) $request['text'], '2026-11-09: qurilmada 1, tizimda 2'));

        $this->fakeTerminal(['2026-11-09' => [[self::ALI, '09:00:00']]]);
        $this->artisan('devices:reconcile-events', ['--days' => 1])->assertSuccessful();

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'sendMessage'));
    }

    public function test_check_is_scheduled_every_night_at_one(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'devices:reconcile-events'));

        $this->assertNotNull($event);
        $this->assertSame('0 1 * * *', $event->expression);
    }

    private function storeEvent(string $employeeNo, string $time): void
    {
        AccessControllerEvent::factory()->create([
            'device_ip' => $this->device->ip_address,
            'employee_no' => $employeeNo,
            'event_time' => $time,
        ]);

        Report::factory()->forEmployee($this->ali)->forDevice($this->device)->entry()->create(['occurred_at' => $time]);
    }

    private function record(string $date, string $status): void
    {
        EventReconciliation::query()->create([
            'device_id' => $this->device->id,
            'date' => $date,
            'status' => $status,
            'checked_at' => now(),
        ]);
    }

    /**
     * @param  array<string, list<array{string, string}>>  $eventsByDay
     */
    private function fakeTerminal(array $eventsByDay): void
    {
        Http::fake(function (Request $request) use ($eventsByDay) {
            if (str_contains($request->url(), 'api.telegram.org')) {
                return Http::response(['ok' => true], 200);
            }

            $day = substr((string) data_get($request->data(), 'AcsEventCond.startTime'), 0, 10);
            $this->requestedDays[] = $day;

            $items = array_map(fn (array $event): array => array_filter([
                'major' => 5,
                'minor' => 75,
                'time' => "{$day}T{$event[1]}+05:00",
                'employeeNoString' => $event[0] !== '' ? $event[0] : null,
                'currentVerifyMode' => 'face',
            ], fn ($value): bool => $value !== null), $eventsByDay[$day] ?? []);

            return Http::response([
                'AcsEvent' => [
                    'responseStatusStrg' => 'OK',
                    'totalMatches' => count($items),
                    'InfoList' => $items,
                ],
            ], 200);
        });
    }
}
