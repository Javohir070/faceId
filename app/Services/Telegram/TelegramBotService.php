<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    public function sendMessage(string $chatId, string $text): bool
    {
        $token = (string) config('telegram.token');

        if ($token === '') {
            Log::warning('Telegram: token bo\'sh, xabar yuborilmadi');

            return false;
        }

        try {
            $response = Http::timeout(15)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                ]);

            if (! $response->successful() || ! ($response->json('ok') ?? false)) {
                Log::warning('Telegram: sendMessage muvaffaqiyatsiz', [
                    'chat_id' => $chatId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Telegram: sendMessage xato', [
                'chat_id' => $chatId,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function setWebhook(string $url, string $secretToken): bool
    {
        $token = (string) config('telegram.token');

        if ($token === '') {
            return false;
        }

        $payload = ['url' => $url];

        if ($secretToken !== '') {
            $payload['secret_token'] = $secretToken;
        }

        try {
            $response = Http::timeout(15)
                ->post("https://api.telegram.org/bot{$token}/setWebhook", $payload);

            return $response->successful() && ($response->json('ok') ?? false);
        } catch (\Throwable $e) {
            Log::warning('Telegram: setWebhook xato', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
