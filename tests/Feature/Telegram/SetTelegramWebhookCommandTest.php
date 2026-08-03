<?php

namespace Tests\Feature\Telegram;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SetTelegramWebhookCommandTest extends TestCase
{
    public function test_sets_webhook_using_app_url_by_default(): void
    {
        config([
            'telegram.token' => 'test-token',
            'telegram.webhook_secret' => 'secret-123',
            'app.url' => 'https://example.test',
        ]);

        Http::fake([
            'https://api.telegram.org/bottest-token/setWebhook' => Http::response(['ok' => true], 200),
        ]);

        $this->artisan('telegram:set-webhook')
            ->assertSuccessful();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'setWebhook')
                && $request['url'] === 'https://example.test/api/telegram/webhook'
                && $request['secret_token'] === 'secret-123';
        });
    }
}
