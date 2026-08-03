<?php

namespace Tests\Unit\Telegram;

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

    public function test_notify_sends_only_to_super_admin_with_matching_prefs(): void
    {
        config(['telegram.token' => 'test-token']);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $this->createTelegramSuperAdmin(['telegram_chat_id' => '111', 'telegram_notify_prefs' => 'all']);
        $this->createTelegramSuperAdmin(['telegram_chat_id' => '222', 'telegram_notify_prefs' => 'device']);
        $this->createTelegramSuperAdmin(['telegram_chat_id' => '333', 'telegram_notify_prefs' => 'expiry']);
        $this->createTelegramSuperAdmin(['telegram_chat_id' => '444', 'telegram_notify_prefs' => 'none']);

        Role::findOrCreate('user', 'web');
        $plain = User::factory()->create(['telegram_chat_id' => '555', 'telegram_notify_prefs' => 'all']);
        $plain->assignRole('user');

        $sent = app(TelegramNotifier::class)->notifyAllLinkedUsers('Salom', TelegramNotifier::CATEGORY_DEVICE);

        $this->assertSame(2, $sent);
        Http::assertSentCount(2);
    }

    public function test_notify_is_noop_when_token_missing(): void
    {
        config(['telegram.token' => '']);

        Http::fake();

        $this->createTelegramSuperAdmin(['telegram_chat_id' => '111']);

        $sent = app(TelegramNotifier::class)->notifyAllLinkedUsers('Salom');

        $this->assertSame(0, $sent);
        Http::assertNothingSent();
    }
}
