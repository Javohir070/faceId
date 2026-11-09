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
    private function message(string $chatId, string $text, int $messageId = 1, string $chatType = 'private'): array
    {
        return [
            'update_id' => 1,
            'message' => [
                'message_id' => $messageId,
                'chat' => ['id' => (int) $chatId, 'type' => $chatType],
                'text' => $text,
                'from' => ['id' => (int) $chatId],
            ],
        ];
    }

    private function tryLogin(string $chatId, string $email, string $password, int $passwordMessageId = 3): void
    {
        $this->webhook($this->message($chatId, '/start', 1))->assertOk();
        $this->webhook($this->message($chatId, $email, 2))->assertOk();
        $this->webhook($this->message($chatId, $password, $passwordMessageId))->assertOk();
    }

    private function sentText(string $needle): bool
    {
        return Http::recorded(fn ($request): bool => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], $needle))->isNotEmpty();
    }

    public function test_password_message_is_deleted_whether_or_not_it_is_correct(): void
    {
        User::factory()->create(['email' => 'admin@example.com', 'password' => 'password']);

        $this->tryLogin('999', 'admin@example.com', 'wrong-pass', 30);
        $this->tryLogin('999', 'admin@example.com', 'password', 31);

        foreach ([30, 31] as $messageId) {
            Http::assertSent(fn ($request): bool => str_contains($request->url(), 'deleteMessage')
                && (string) $request['chat_id'] === '999'
                && (int) $request['message_id'] === $messageId);
        }

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'deleteMessage')
            && (int) $request['message_id'] === 2);
    }

    public function test_chat_is_locked_after_five_wrong_passwords(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com', 'password' => 'password']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->tryLogin('999', 'admin@example.com', 'wrong-'.$attempt);
        }

        $this->tryLogin('999', 'admin@example.com', 'password');

        $this->assertNull($user->fresh()->telegram_chat_id);
        $this->assertTrue($this->sentText('Juda ko‘p noto‘g‘ri urinish'));
    }

    public function test_email_is_locked_after_five_wrong_passwords_from_any_chats(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com', 'password' => 'password']);

        foreach (['101', '102', '103', '104', '105'] as $chatId) {
            $this->tryLogin($chatId, 'admin@example.com', 'wrong');
        }

        $this->tryLogin('106', 'admin@example.com', 'password');

        $this->assertNull($user->fresh()->telegram_chat_id);
    }

    public function test_successful_login_resets_the_counter(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com', 'password' => 'password']);

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->tryLogin('999', 'admin@example.com', 'wrong-'.$attempt);
        }

        $this->tryLogin('999', 'admin@example.com', 'password');
        $this->assertSame('999', $user->fresh()->telegram_chat_id);

        $this->webhook($this->message('999', '/unlink'))->assertOk();

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->tryLogin('999', 'admin@example.com', 'wrong-again-'.$attempt);
        }

        $this->tryLogin('999', 'admin@example.com', 'password');
        $this->assertSame('999', $user->fresh()->telegram_chat_id);
    }

    public function test_login_never_starts_in_a_group(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com', 'password' => 'password']);

        $this->webhook($this->message('-100500', '/start', 1, 'supergroup'))->assertOk();
        $this->webhook($this->message('-100500', 'admin@example.com', 2, 'supergroup'))->assertOk();
        $this->webhook($this->message('-100500', 'password', 3, 'supergroup'))->assertOk();

        $this->assertNull($user->fresh()->telegram_chat_id);
        $this->assertFalse($this->sentText('Email kiriting'));
        $this->assertFalse($this->sentText('Parol kiriting'));
        $this->assertTrue($this->sentText('shaxsiy chat'));
    }

    public function test_bot_stays_silent_on_ordinary_group_messages(): void
    {
        $this->webhook($this->message('-100500', 'Salom hammaga', 1, 'group'))->assertOk();

        Http::assertNothingSent();
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
