<?php

namespace Tests\Unit\Telegram;

use App\Models\Category;
use App\Models\Device;
use App\Models\User;
use App\Services\Telegram\TelegramNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesTelegramAdmin;
use Tests\TestCase;

class TelegramNotifierTest extends TestCase
{
    use CreatesTelegramAdmin;
    use RefreshDatabase;

    public function test_device_alert_respects_each_chats_notification_filter(): void
    {
        config(['telegram.token' => 'test-token']);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $this->createTelegramSuperAdmin(['telegram_chat_id' => '111', 'telegram_notify_prefs' => 'all']);
        $this->createTelegramSuperAdmin(['telegram_chat_id' => '222', 'telegram_notify_prefs' => 'device']);
        $this->createTelegramSuperAdmin(['telegram_chat_id' => '333', 'telegram_notify_prefs' => 'expiry']);
        $this->createTelegramSuperAdmin(['telegram_chat_id' => '444', 'telegram_notify_prefs' => 'none']);

        // Qurilma unga tegishli emas: xabar olmaydi.
        Role::findOrCreate('user', 'web');
        $plain = User::factory()->create(['telegram_chat_id' => '555', 'telegram_notify_prefs' => 'all']);
        $plain->assignRole('user');

        $device = Device::factory()->create([
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
        ]);

        $sent = app(TelegramNotifier::class)->notifyDevice($device, 'Salom');

        $this->assertSame(2, $sent);
        Http::assertSentCount(2);
    }

    public function test_notify_is_noop_when_token_missing(): void
    {
        config(['telegram.token' => '']);

        Http::fake();

        $this->createTelegramSuperAdmin(['telegram_chat_id' => '111']);

        $device = Device::factory()->create([
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
        ]);

        $this->assertSame(0, app(TelegramNotifier::class)->notifyDevice($device, 'Salom'));
        $this->assertSame(0, app(TelegramNotifier::class)->notifyEachLinkedUser(TelegramNotifier::CATEGORY_DIGEST, fn (): string => 'Salom'));
        Http::assertNothingSent();
    }
}
