<?php

namespace Tests\Unit\Telegram;

use App\Models\User;
use App\Services\Telegram\TelegramNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_notify_all_linked_users_sends_to_each_chat(): void
    {
        config(['telegram.token' => 'test-token']);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        User::factory()->create(['telegram_chat_id' => '111']);
        User::factory()->create(['telegram_chat_id' => '222']);
        User::factory()->create(['telegram_chat_id' => null]);

        $sent = app(TelegramNotifier::class)->notifyAllLinkedUsers('Salom');

        $this->assertSame(2, $sent);
        Http::assertSentCount(2);
    }

    public function test_notify_is_noop_when_token_missing(): void
    {
        config(['telegram.token' => '']);

        Http::fake();

        User::factory()->create(['telegram_chat_id' => '111']);

        $sent = app(TelegramNotifier::class)->notifyAllLinkedUsers('Salom');

        $this->assertSame(0, $sent);
        Http::assertNothingSent();
    }
}
