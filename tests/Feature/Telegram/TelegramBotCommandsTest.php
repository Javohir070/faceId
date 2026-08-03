<?php

namespace Tests\Feature\Telegram;

use App\Models\Category;
use App\Models\Device;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesTelegramAdmin;
use Tests\TestCase;

class TelegramBotCommandsTest extends TestCase
{
    use CreatesTelegramAdmin;
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
    private function webhook(array $payload)
    {
        return $this->postJson('/api/telegram/webhook', $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => 'secret-123',
        ]);
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

    public function test_status_command_for_super_admin(): void
    {
        $this->createTelegramSuperAdmin(['telegram_chat_id' => '999']);
        $owner = User::factory()->create();
        $category = Category::factory()->create();
        Device::factory()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'is_online' => false,
        ]);

        $this->webhook($this->message('999', '/status'))->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains((string) $request['text'], 'Holat');
        });
    }

    public function test_settings_updates_prefs(): void
    {
        $user = $this->createTelegramSuperAdmin(['telegram_chat_id' => '999', 'telegram_notify_prefs' => 'all']);

        $this->webhook($this->message('999', '/settings'))->assertOk();
        $this->webhook($this->message('999', '2'))->assertOk();

        $this->assertSame('device', $user->fresh()->telegram_notify_prefs);
    }

    public function test_non_admin_cannot_use_status(): void
    {
        Role::findOrCreate('user', 'web');
        $user = User::factory()->create(['telegram_chat_id' => '999']);
        $user->assignRole('user');

        $this->webhook($this->message('999', '/status'))->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains((string) $request['text'], 'super-admin');
        });
    }

    public function test_expiry_command_lists_employees(): void
    {
        $this->createTelegramSuperAdmin(['telegram_chat_id' => '999']);
        Employee::factory()->create([
            'name' => 'Ali',
            'valid_to_dt' => now()->addDay(),
        ]);

        $this->webhook($this->message('999', '/expiry'))->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains((string) $request['text'], 'Ali');
        });
    }
}
