<?php

namespace Tests\Feature\Telegram;

use App\Models\Category;
use App\Models\Department;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\TelegramGroup;
use App\Models\User;
use App\Services\Hikvision\HikvisionDeviceService;
use App\Services\Telegram\TelegramNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesTelegramAdmin;
use Tests\TestCase;

class TelegramGroupAndScopeTest extends TestCase
{
    use CreatesTelegramAdmin;
    use RefreshDatabase;

    private Department $own;

    private Department $other;

    private User $admin;

    private User $user;

    private User $stranger;

    private Device $device;

    private Device $foreignDevice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-11 08:30'));

        config([
            'telegram.token' => 'test-token',
            'telegram.webhook_secret' => 'secret-123',
            'telegram.employee_expiry_warn_days' => 3,
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $this->own = Department::factory()->create(['name' => 'Nukus']);
        $this->other = Department::factory()->create(['name' => 'Xiva']);
        $this->admin = $this->createTelegramSuperAdmin(['telegram_chat_id' => '111']);
        $this->user = $this->linkedUser('222', $this->own);
        $this->stranger = $this->linkedUser('333', $this->other);

        $this->device = $this->device('Nukus-1', $this->user);
        $this->foreignDevice = $this->device('Xiva-1', $this->stranger);
    }

    public function test_linked_user_binds_a_group_with_ulash(): void
    {
        $this->webhook($this->groupMessage('-1001', '222', '/ulash@BiocontrolBot'))->assertOk();

        $this->assertDatabaseHas('telegram_groups', ['chat_id' => '-1001', 'user_id' => $this->user->id, 'title' => 'Nukus guruhi']);
        $this->assertTrue($this->sentTo('-1001', 'Guruh ulandi'));
    }

    public function test_member_who_is_not_linked_cannot_bind_a_group(): void
    {
        $this->webhook($this->groupMessage('-1001', '999', '/ulash'))->assertOk();

        $this->assertDatabaseMissing('telegram_groups', ['chat_id' => '-1001']);
        $this->assertTrue($this->sentTo('-1001', 'shaxsiy chatda'));
    }

    public function test_only_the_binder_or_a_super_admin_can_unbind(): void
    {
        $this->webhook($this->groupMessage('-1001', '222', '/ulash'))->assertOk();

        $this->webhook($this->groupMessage('-1001', '333', '/uzish'))->assertOk();
        $this->assertDatabaseHas('telegram_groups', ['chat_id' => '-1001']);

        $this->webhook($this->groupMessage('-1001', '111', '/uzish'))->assertOk();
        $this->assertDatabaseMissing('telegram_groups', ['chat_id' => '-1001']);
    }

    public function test_group_is_forgotten_when_the_bot_is_removed(): void
    {
        TelegramGroup::query()->create(['chat_id' => '-1001', 'user_id' => $this->user->id]);

        $this->webhook([
            'update_id' => 5,
            'my_chat_member' => [
                'chat' => ['id' => -1001, 'type' => 'supergroup'],
                'new_chat_member' => ['status' => 'kicked'],
            ],
        ])->assertOk();

        $this->assertDatabaseMissing('telegram_groups', ['chat_id' => '-1001']);
    }

    public function test_device_alert_reaches_only_people_and_groups_that_see_the_device(): void
    {
        TelegramGroup::query()->create(['chat_id' => '-1001', 'user_id' => $this->user->id]);
        TelegramGroup::query()->create(['chat_id' => '-1002', 'user_id' => $this->stranger->id]);
        TelegramGroup::query()->create(['chat_id' => '-1003', 'user_id' => $this->admin->id]);

        $sent = app(TelegramNotifier::class)->notifyDevice($this->device, 'Nukus-1 uzildi');

        $this->assertSame(4, $sent);

        foreach (['111', '222', '-1001', '-1003'] as $chatId) {
            $this->assertTrue($this->sentTo($chatId, 'Nukus-1 uzildi'), "{$chatId} ga xabar bormadi");
        }

        foreach (['333', '-1002'] as $chatId) {
            $this->assertFalse($this->sentTo($chatId, 'Nukus-1 uzildi'), "{$chatId} ga xabar bormasligi kerak edi");
        }
    }

    public function test_device_linked_to_a_user_counts_as_theirs(): void
    {
        $this->foreignDevice->users()->attach($this->user->id);

        app(TelegramNotifier::class)->notifyDevice($this->foreignDevice, 'Xiva-1 uzildi');

        $this->assertTrue($this->sentTo('222', 'Xiva-1 uzildi'));
    }

    public function test_offline_ping_sends_the_alert_to_the_bound_group(): void
    {
        config(['hikvision.ping_offline_after_failures' => 1]);
        Http::fake(['*' => Http::response('down', 500)]);
        TelegramGroup::query()->create(['chat_id' => '-1001', 'user_id' => $this->user->id]);

        app(HikvisionDeviceService::class)->refreshOnlineStatus($this->device);

        $this->assertTrue($this->sentTo('-1001', 'Nukus-1'));
        $this->assertFalse($this->sentTo('333', 'Nukus-1'));
    }

    public function test_user_only_gets_expiry_messages_of_their_department(): void
    {
        $this->employee('Nukus xodimi', $this->own, ['valid_to_dt' => now()->addDays(2)]);
        $this->employee('Xiva xodimi', $this->other, ['valid_to_dt' => now()->addDays(2)]);

        $this->artisan('employees:notify-expiry')->assertSuccessful();

        $this->assertTrue($this->sentTo('222', 'Nukus xodimi'));
        $this->assertFalse($this->sentTo('222', 'Xiva xodimi'));
        $this->assertTrue($this->sentTo('333', 'Xiva xodimi'));
        $this->assertTrue($this->sentTo('111', 'Nukus xodimi'));
        $this->assertTrue($this->sentTo('111', 'Xiva xodimi'));
    }

    public function test_daily_digest_is_built_for_each_user(): void
    {
        $schedule = Schedule::factory()->create(['enter_time' => '09:00', 'exit_time' => '18:00']);
        $this->employee('Nukus xodimi', $this->own, ['schedule_id' => $schedule->id]);
        $this->employee('Xiva xodimi', $this->other, ['schedule_id' => $schedule->id]);

        $this->artisan('telegram:daily-digest')->assertSuccessful();

        $this->assertTrue($this->sentTo('222', 'Nukus xodimi'));
        $this->assertFalse($this->sentTo('222', 'Xiva xodimi'));
        $this->assertTrue($this->sentTo('111', 'Nukus xodimi'));
        $this->assertTrue($this->sentTo('111', 'Xiva xodimi'));
    }

    public function test_user_buttons_show_only_their_own_data(): void
    {
        $this->device->update(['is_online' => false]);
        $this->foreignDevice->update(['is_online' => false]);
        $this->employee('Nukus xodimi', $this->own, ['valid_to_dt' => now()->addDay()]);
        $this->employee('Xiva xodimi', $this->other, ['valid_to_dt' => now()->addDay()]);

        $this->webhook($this->privateMessage('222', '/devices'))->assertOk();
        $this->webhook($this->privateMessage('222', '/expiry'))->assertOk();
        $this->webhook($this->privateMessage('222', '/status'))->assertOk();

        $this->assertTrue($this->sentTo('222', 'Nukus-1'));
        $this->assertFalse($this->sentTo('222', 'Xiva-1'));
        $this->assertTrue($this->sentTo('222', 'Nukus xodimi'));
        $this->assertFalse($this->sentTo('222', 'Xiva xodimi'));
        $this->assertTrue($this->sentTo('222', 'Qurilma: 0 online, 1 oflayn'));
        $this->assertFalse($this->sentTo('222', 'super-admin'));
    }

    public function test_user_can_choose_their_notification_filter(): void
    {
        $this->webhook([
            'update_id' => 7,
            'callback_query' => [
                'id' => 'cb-1',
                'data' => 'pref:device',
                'message' => ['chat' => ['id' => 222, 'type' => 'private']],
            ],
        ])->assertOk();

        $this->assertSame('device', $this->user->fresh()->telegram_notify_prefs);
    }

    private function linkedUser(string $chatId, Department $department): User
    {
        Role::findOrCreate('user', 'web');

        $user = User::factory()->create([
            'telegram_chat_id' => $chatId,
            'telegram_notify_prefs' => 'all',
            'department_id' => $department->id,
        ]);
        $user->assignRole('user');

        return $user->fresh();
    }

    private function device(string $name, User $owner): Device
    {
        return Device::factory()->create([
            'user_id' => $owner->id,
            'category_id' => Category::factory(),
            'name' => $name,
            'is_online' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function employee(string $name, Department $department, array $attributes = []): void
    {
        Employee::factory()->create([
            'name' => $name,
            'department_id' => $department->id,
            'valid_from_dt' => now()->subMonth(),
            'valid_to_dt' => now()->addMonth(),
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function webhook(array $payload)
    {
        return $this->postJson('/api/telegram/webhook', $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => 'secret-123',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function groupMessage(string $chatId, string $fromId, string $text): array
    {
        return [
            'update_id' => 1,
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => (int) $chatId, 'type' => 'supergroup', 'title' => 'Nukus guruhi'],
                'from' => ['id' => (int) $fromId],
                'text' => $text,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function privateMessage(string $chatId, string $text): array
    {
        return [
            'update_id' => 1,
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => (int) $chatId, 'type' => 'private'],
                'from' => ['id' => (int) $chatId],
                'text' => $text,
            ],
        ];
    }

    private function sentTo(string $chatId, string $needle): bool
    {
        return Http::recorded(fn ($request): bool => str_contains($request->url(), 'sendMessage')
            && (string) $request['chat_id'] === $chatId
            && str_contains((string) $request['text'], $needle))->isNotEmpty();
    }
}
