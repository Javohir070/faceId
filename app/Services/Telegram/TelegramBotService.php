<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    public ?string $lastError = null;

    /**
     * @param  array<string, mixed>|null  $replyMarkup
     */
    public function sendMessage(string $chatId, string $text, ?array $replyMarkup = null): bool
    {
        $this->lastError = null;
        $token = (string) config('telegram.token');

        if ($token === '') {
            $this->lastError = 'TELEGRAM_BOT_TOKEN bo\'sh';
            Log::warning('Telegram: token bo\'sh, xabar yuborilmadi');

            return false;
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = json_encode($replyMarkup, JSON_UNESCAPED_UNICODE);
        }

        try {
            $response = $this->http()
                ->post("https://api.telegram.org/bot{$token}/sendMessage", $payload);

            if (! $response->successful() || ! ($response->json('ok') ?? false)) {
                $this->lastError = $response->body();
                Log::warning('Telegram: sendMessage muvaffaqiyatsiz', [
                    'chat_id' => $chatId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            Log::warning('Telegram: sendMessage xato', [
                'chat_id' => $chatId,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null): bool
    {
        $token = (string) config('telegram.token');

        if ($token === '' || $callbackQueryId === '') {
            return false;
        }

        $payload = ['callback_query_id' => $callbackQueryId];

        if ($text !== null && $text !== '') {
            $payload['text'] = $text;
        }

        try {
            $response = $this->http()
                ->post("https://api.telegram.org/bot{$token}/answerCallbackQuery", $payload);

            return $response->successful() && ($response->json('ok') ?? false);
        } catch (\Throwable) {
            return false;
        }
    }

    public function setWebhook(string $url, string $secretToken): bool
    {
        $this->lastError = null;
        $token = (string) config('telegram.token');

        if ($token === '') {
            $this->lastError = 'TELEGRAM_BOT_TOKEN bo\'sh';

            return false;
        }

        $payload = ['url' => $url];

        if ($secretToken !== '') {
            $payload['secret_token'] = $secretToken;
        }

        try {
            $response = $this->http()
                ->post("https://api.telegram.org/bot{$token}/setWebhook", $payload);

            if ($response->successful() && ($response->json('ok') ?? false)) {
                return true;
            }

            $this->lastError = $response->json('description') ?? $response->body();

            return false;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            Log::warning('Telegram: setWebhook xato', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function http(): PendingRequest
    {
        $request = Http::timeout(15);

        if (! (bool) config('telegram.http_verify', true)) {
            $request = $request->withOptions(['verify' => false]);
        }

        return $request;
    }
}
