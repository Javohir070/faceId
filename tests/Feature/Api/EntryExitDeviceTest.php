<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Report;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

/**
 * Bitta terminal ham kirish, ham chiqish ("Kirish va chiqish" turi).
 */
class EntryExitDeviceTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['hikvision.webhook_secret' => 'test-webhook-secret']);
    }

    private function device(int $type = Device::TYPE_BOTH, string $ip = '10.0.0.40'): Device
    {
        return Device::factory()->create([
            'user_id' => User::factory()->create()->id,
            'category_id' => Category::factory()->create()->id,
            'ip_address' => $ip,
            'type' => $type,
        ]);
    }

    private function pass(string $ip, string $card, string $time, ?string $status = null): void
    {
        $this->postJson('/api/webhooks/hikvision/events', [
            'event_log' => json_encode([
                'ipAddress' => $ip,
                'dateTime' => $time.'+05:00',
                'AccessControllerEvent' => array_filter([
                    'employeeNoString' => $card,
                    'majorEventType' => 5,
                    'subEventType' => 75,
                    'currentVerifyMode' => 'face',
                    'attendanceStatus' => $status,
                ], fn ($value) => $value !== null),
            ]),
        ], ['X-Webhook-Secret' => 'test-webhook-secret'])->assertOk();
    }

    /**
     * @return list<string>
     */
    private function types(Employee $employee): array
    {
        return Report::query()
            ->where('employee_id', $employee->id)
            ->orderBy('occurred_at')
            ->pluck('type')
            ->map(fn (int $type): string => $type === Report::TYPE_ENTRY ? 'kirdi' : 'chiqdi')
            ->all();
    }

    public function test_without_button_passes_alternate_within_a_work_day(): void
    {
        $this->device();
        $employee = Employee::factory()->create(['card' => '900']);

        $this->pass('10.0.0.40', '900', '2026-11-02T09:00:00');
        $this->pass('10.0.0.40', '900', '2026-11-02T13:00:00');
        $this->pass('10.0.0.40', '900', '2026-11-02T13:50:00');
        $this->pass('10.0.0.40', '900', '2026-11-02T18:05:00');
        // Kecha chiqishni unutgan bo‘lsa ham yangi kun kirishdan boshlanadi.
        $this->pass('10.0.0.40', '900', '2026-11-03T09:00:00');

        $this->assertSame(['kirdi', 'chiqdi', 'kirdi', 'chiqdi', 'kirdi'], $this->types($employee));
    }

    public function test_button_on_terminal_wins_over_alternation(): void
    {
        $this->device();
        $employee = Employee::factory()->create(['card' => '901']);

        $this->pass('10.0.0.40', '901', '2026-11-02T09:00:00', 'checkOut');
        $this->pass('10.0.0.40', '901', '2026-11-02T10:00:00', 'checkOut');
        $this->pass('10.0.0.40', '901', '2026-11-02T11:00:00');

        $this->assertSame(['chiqdi', 'chiqdi', 'kirdi'], $this->types($employee));
    }

    public function test_repeated_recognition_within_two_minutes_does_not_flip(): void
    {
        $this->device();
        $employee = Employee::factory()->create(['card' => '902']);

        $this->pass('10.0.0.40', '902', '2026-11-02T09:00:00');
        $this->pass('10.0.0.40', '902', '2026-11-02T09:00:40');
        $this->pass('10.0.0.40', '902', '2026-11-02T09:01:50');
        $this->pass('10.0.0.40', '902', '2026-11-02T12:30:00');

        $this->assertSame(['kirdi', 'kirdi', 'kirdi', 'chiqdi'], $this->types($employee));
    }

    public function test_alternation_counts_passes_on_other_devices(): void
    {
        $this->device(Device::TYPE_ENTRY, '10.0.0.41');
        $this->device();
        $employee = Employee::factory()->create(['card' => '903']);

        $this->pass('10.0.0.41', '903', '2026-11-02T08:55:00');
        $this->pass('10.0.0.40', '903', '2026-11-02T18:00:00');

        $this->assertSame(['kirdi', 'chiqdi'], $this->types($employee));
    }

    public function test_night_shift_exit_after_midnight_belongs_to_the_same_work_day(): void
    {
        $this->device();
        $employee = Employee::factory()->create([
            'card' => '904',
            'schedule_id' => Schedule::factory()->create(['enter_time' => '20:00:00', 'exit_time' => '08:00:00'])->id,
        ]);

        $this->pass('10.0.0.40', '904', '2026-11-02T20:05:00');
        $this->pass('10.0.0.40', '904', '2026-11-03T07:58:00');
        $this->pass('10.0.0.40', '904', '2026-11-03T20:02:00');

        $this->assertSame(['kirdi', 'chiqdi', 'kirdi'], $this->types($employee));
    }

    public function test_device_can_be_saved_and_filtered_as_entry_and_exit(): void
    {
        $admin = $this->createUserWithPermissions([config('menu_permissions.resources')['devices']]);
        $category = Category::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.devices.store'), [
                'category_id' => $category->id,
                'name' => 'Ofis terminali',
                'ip_address' => '192.168.1.50',
                'port' => 80,
                'user' => 'admin',
                'password' => 'secret',
                'type' => Device::TYPE_BOTH,
            ])
            ->assertRedirect(route('admin.devices.index'));

        $this->actingAs($admin)
            ->get(route('admin.devices.index', ['type' => Device::TYPE_BOTH]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('devices.data', 1)
                ->where('devices.data.0.type', Device::TYPE_BOTH)
                ->where('devices.data.0.type_label', 'Kirish va chiqish'));
    }
}
