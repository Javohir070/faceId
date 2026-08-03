<?php

namespace Tests\Feature\Telegram;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramWebhookAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'telegram.token' => 'test-token',
            'telegram.webhook_secret' => 'secret-123',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function webhook(array $payload, ?string $secret = 'secret-123')
    {
        $headers = $secret === null
            ? []
            : ['X-Telegram-Bot-Api-Secret-Token' => $secret];

        return $this->postJson('/api/telegram/webhook', $payload, $headers);
    }

    /**
     * @return array<string, mixed>
     */
    private function message(string $chatId, string $text): array
    {
        return [
            'update_id' => 1,
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => (int) $chatId, 'type' => 'private'],
                'text' => $text,
                'from' => ['id' => (int) $chatId],
            ],
        ];
    }

    public function test_rejects_invalid_webhook_secret(): void
    {
        $this->webhook($this->message('100', '/start'), 'wrong')->assertForbidden();
    }

    public function test_login_success_stores_telegram_chat_id(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $this->webhook($this->message('999', '/start'))->assertOk();
        $this->webhook($this->message('999', 'admin@example.com'))->assertOk();
        $this->webhook($this->message('999', 'password'))->assertOk();

        $this->assertSame('999', $user->fresh()->telegram_chat_id);
    }

    public function test_wrong_password_does_not_store_chat_id(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $this->webhook($this->message('999', '/start'))->assertOk();
        $this->webhook($this->message('999', 'admin@example.com'))->assertOk();
        $this->webhook($this->message('999', 'wrong-pass'))->assertOk();

        $this->assertNull($user->fresh()->telegram_chat_id);
    }

    public function test_unlink_clears_chat_id(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => '999']);

        $this->webhook($this->message('999', '/unlink'))->assertOk();

        $this->assertNull($user->fresh()->telegram_chat_id);
    }
}
